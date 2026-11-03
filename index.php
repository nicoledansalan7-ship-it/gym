<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
if (!$conn) die("Database connection failed.");

$page = $_GET['page'] ?? 'login';

$msg = "";
$type = "";
$remaining_attempts = null;

function clean($conn, $value) {
    return mysqli_real_escape_string($conn, trim((string)$value));
}

function columnExists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($check && mysqli_num_rows($check) > 0);
}

function ensureUsersTable($conn) {
    $createTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'member',
            membership_plan VARCHAR(100) NOT NULL DEFAULT 'Standard',
            status VARCHAR(20) NOT NULL DEFAULT 'Active',
            member_since DATE DEFAULT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            deactivation_reason VARCHAR(50) NOT NULL DEFAULT 'none',
            locked_until DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    mysqli_query($conn, $createTable);

    $columns = [
        'email' => "ALTER TABLE users ADD COLUMN email VARCHAR(150) DEFAULT NULL",
        'role' => "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'member'",
        'membership_plan' => "ALTER TABLE users ADD COLUMN membership_plan VARCHAR(100) NOT NULL DEFAULT 'Standard'",
        'status' => "ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'",
        'member_since' => "ALTER TABLE users ADD COLUMN member_since DATE DEFAULT NULL",
        'failed_attempts' => "ALTER TABLE users ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0",
        'is_active' => "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        'deactivation_reason' => "ALTER TABLE users ADD COLUMN deactivation_reason VARCHAR(50) NOT NULL DEFAULT 'none'",
        'locked_until' => "ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL"
    ];

    foreach ($columns as $column => $query) {
        if (!columnExists($conn, 'users', $column)) {
            mysqli_query($conn, $query);
        }
    }
}

ensureUsersTable($conn);

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

if (isset($_GET['deactivated'])) {
    if ($_GET['deactivated'] === 'admin') {
        $msg = "Your account has been deactivated by the admin.";
    } elseif ($_GET['deactivated'] === 'failed_attempts') {
        $msg = "Your account has been suspended due to 3 failed login attempts. Please contact the admin for reactivation.";
    }
    $type = "error";
}

/* LOGIN */
if (isset($_POST['login'])) {
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    if ($usernameInput === '' || $passwordInput === '') {
        $msg = "Please enter both username and password.";
        $type = "error";
    } else {
        $username = clean($conn, $usernameInput);
        $result = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' LIMIT 1");

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            $userId = (int)$user['id'];
            $role = strtolower(trim($user['role'] ?? 'member'));
            $isActive = (int)($user['is_active'] ?? 1);
            $deactivationReason = $user['deactivation_reason'] ?? 'none';
            $failedAttempts = (int)($user['failed_attempts'] ?? 0);

            /* BLOCK DEACTIVATED OR SUSPENDED ACCOUNTS */
            if ($isActive === 0 && $deactivationReason === 'admin') {
                header("Location: index.php?deactivated=admin");
                exit();
            }

            if ($role !== 'admin' && $isActive === 0 && $deactivationReason === 'failed_attempts') {
                header("Location: index.php?deactivated=failed_attempts");
                exit();
            }

            /* PASSWORD CHECK */
            if (password_verify($passwordInput, $user['password'])) {
                // Successful login: reset failed attempts
                mysqli_query($conn, "
                    UPDATE users
                    SET failed_attempts = 0,
                        deactivation_reason = 'none',
                        locked_until = NULL,
                        status = 'Active',
                        is_active = 1
                    WHERE id = $userId
                ");

                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $user['full_name'] ?? '';

                if ($role === 'admin') {
                    header("Location: admin/admin_dashboard.php");
                    exit();
                } else {
                    header("Location: user/user_dashboard.php");
                    exit();
                }

            } else {
                // Incorrect password
                if ($role === 'admin') {
                    $msg = "Invalid username or password.";
                    $type = "error";
                } else {
                    // Increment failed attempts
                    $failedAttempts++;
                    $remaining_attempts = max(0, 3 - $failedAttempts);

                    if ($failedAttempts >= 3) {
                        // Suspend account
                        mysqli_query($conn, "
                            UPDATE users
                            SET failed_attempts = 3,
                                is_active = 0,
                                status = 'Deactivated',
                                deactivation_reason = 'failed_attempts'
                            WHERE id = $userId
                        ");

                        header("Location: index.php?deactivated=failed_attempts");
                        exit();

                    } else {
                        // Update failed attempts in DB
                        mysqli_query($conn, "
                            UPDATE users
                            SET failed_attempts = $failedAttempts
                            WHERE id = $userId
                        ");

                        $msg = "Invalid username or password. Remaining attempts: " . $remaining_attempts;
                        $type = "error";
                    }
                }
            }
        } else {
            $msg = "Invalid username or password.";
            $type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vigor Gym</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
body {
    min-height: 100vh;
    font-family: 'Poppins', sans-serif;
    background-image: url('bg.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 22px;
    color: #fff;
    overflow-x: hidden;
}
.auth-page {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: 20px;
}
.auth-box {
    margin-top: 30px;
    width: 100%;
    max-width: 700px;
    min-height: 600px;
    border-radius: 16px;
    overflow: hidden;
    background: linear-gradient(180deg, #1b042c 0%, #0a0112 100%);
    border: 1px solid rgba(180, 100, 255, 0.16);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.50), 0 0 0 1px rgba(255,255,255,0.02) inset, 0 0 36px rgba(162, 53, 255, 0.14);
}
.logo-container {
    min-height: 78px;
    padding: 12px 24px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(180deg, rgba(78, 18, 134, 0.90), rgba(34, 5, 61, 0.96));
    border-bottom: 1px solid rgba(194, 98, 255, 0.18);
}
.header-logo {
    width: 100%;
    max-width: 460px;
    height: 80px;
    object-fit: contain;
    display: block;
}
.form-wrap {
    padding: 24px;
    background: linear-gradient(180deg, rgba(51, 9, 94, 0.18), rgba(12, 2, 24, 0.05)), linear-gradient(180deg, #12021e 0%, #11011b 100%);
}
.form-card {
    margin-top: 15px;
    background: linear-gradient(180deg, rgba(25, 4, 40, 0.96) 0%, rgba(11, 1, 19, 0.98) 100%);
    border: 1px solid rgba(171, 104, 255, 0.14);
    border-radius: 12px;
    padding: 26px 30px 22px;
    box-shadow: inset 0 0 18px rgba(186, 84, 255, 0.05), 0 10px 24px rgba(0, 0, 0, 0.28);
}
.form-card h2 {
    text-align: center;
    color: #ffe6ff;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 80px;
    margin-top: 30px;
    line-height: 1.1;
}
.glow-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(191, 95, 255, 0.52), transparent);
    margin: 0 0 24px;
    box-shadow: 0 0 12px rgba(191, 95, 255, 0.18);
}
.input-box {
    display: grid;
    grid-template-columns: 145px minmax(3, 4fr);
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}
.input-box label {
    font-size: 15px;
    color: #f3ddff;
    text-align: left;
    font-weight: 600;
}
.input-box input,
.input-box select {
    width: 100%;
    height: 44px;
    padding: 8px 14px;
    border: 1px solid rgba(136, 58, 214, 0.72);
    border-radius: 8px;
    background: rgba(18, 5, 30, 0.84);
    color: #ffffff;
    font-size: 25px;
    font-family: 'Poppins', sans-serif;
    outline: none;
    box-shadow: inset 0 0 8px rgba(0,0,0,0.24), 0 0 8px rgba(139,49,218,0.08);
    transition: all 0.2s ease;
}
.input-box input:focus,
.input-box select:focus {
    border-color: #c86aff;
    box-shadow: 0 0 0 1px rgba(200, 106, 255, 0.12), 0 0 12px rgba(200, 106, 255, 0.18), inset 0 0 8px rgba(0,0,0,0.24);
}
.input-box input::placeholder {
    color: #b89ad3;
}
.input-box select option {
    color: #000;
}
.btn-wrap {
    text-align: center;
    margin: 40px 0 0;
}
.btn {
    min-width: 190px;
    height: 44px;
    padding: 0;
    border: none;
    border-radius: 12px;
    background: linear-gradient(180deg, #ca7bff 0%, #ab52f2 52%, #7d35df 100%);
    color: #fff4ff;
    font-size: 15px;
    font-weight: 700;
    font-family: 'Poppins', sans-serif;
    letter-spacing: 0.4px;
    cursor: pointer;
    box-shadow: 0 0 0 1px rgba(255,255,255,0.10) inset, 0 8px 18px rgba(168, 69, 255, 0.28), 0 0 18px rgba(194, 97, 255, 0.24);
    transition: 0.2s ease;
}
.btn:hover {
    transform: translateY(-1px);
}
.msg {
    text-align: center;
    margin-top: 18px;
    padding: 12px 16px;
    font-size: 14px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,0.05);
    background: rgba(255,255,255,0.03);
}
.msg.success {
    color: #f5deff;
    box-shadow: 0 0 10px rgba(194,97,255,0.08);
}
.msg.error {
    color: #ffb0d2;
    box-shadow: 0 0 10px rgba(255,120,180,0.06);
}
</style>
</head>
<body>

<div class="auth-page">
    <div class="auth-box">
        <div class="logo-container">
            <img src="vigor-header.png" alt="Vigor Gym" class="header-logo">
        </div>

        <div class="form-wrap">
            <div class="form-card">
                <h2>
                <?php
                if ($page === 'login') {
                    echo "Login";
                }
                ?>
                </h2>
                <div class="glow-divider"></div>

                <?php if ($page === 'login') { ?>
    <!-- LOGIN FORM -->
    <form method="post">
        <div class="input-box">
            <label for="login_username">Username:</label>
            <input type="text" id="login_username" name="username" autocomplete="off">
        </div>

        <div class="input-box">
            <label for="login_password">Password:</label>
            <input type="password" id="login_password" name="password" autocomplete="new-password">
        </div>

        <div class="btn-wrap">
            <button type="submit" name="login" class="btn">Login</button>
        </div>
    </form>
                <?php } ?>

                <?php if ($msg != "") { ?>
                    <div class="msg <?php echo $type; ?>"><?php echo $msg; ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>