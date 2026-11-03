<?php
session_start();

$sessionRole = strtolower(trim($_SESSION['role'] ?? ''));

if (!isset($_SESSION['user_id']) || $sessionRole !== 'admin') {
    header("Location: index.php?page=login");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
if (!$conn) {
    die("Database connection failed.");
}

// Count all active memberships excluding admins
$activeSubscriptions = (int) fetchValue(
    $conn,
    "SELECT COUNT(*) AS total 
     FROM users 
     WHERE status = 'Active' 
       AND is_active = 1 
       AND role != 'admin'",
    'total'
);

mysqli_set_charset($conn, "utf8mb4");

function fetchValue($conn, $query, $key)
{
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("Query failed: " . mysqli_error($conn) . "<br>SQL: " . $query);
    }

    $row = mysqli_fetch_assoc($result);
    return $row[$key] ?? 0;
}

function safe($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function renderRows($result, $columns, $prefixes = [])
{
    if (!$result) {
        echo "<tr><td colspan='" . count($columns) . "'>Query failed.</td></tr>";
        return;
    }

    if (mysqli_num_rows($result) === 0) {
        echo "<tr><td colspan='" . count($columns) . "'>No records found.</td></tr>";
        return;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";

        foreach ($columns as $column) {
            $prefix = $prefixes[$column] ?? '';
            $value = $row[$column] ?? '';

            if (in_array($column, ['check_in_time', 'check_out_time'], true) && !empty($value)) {
                $value = date("F d, Y h:i A", strtotime($value . " -8 hours"));
            }

            echo "<td>" . safe($prefix . $value) . "</td>";
        }

        echo "</tr>";
    }
}

$stats = [
    ['Total Members', fetchValue($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'member'", 'total')],
    ['Active Subscriptions', $activeSubscriptions],
    ['Daily Check-ins', fetchValue($conn, "SELECT COUNT(*) AS checkins FROM attendance WHERE DATE(check_in_time) = CURDATE()", 'checkins')],
    ['Income Today', '₱' . number_format((float) fetchValue($conn, "SELECT COALESCE(SUM(amount), 0) AS income FROM payments WHERE DATE(payment_date) = CURDATE()", 'income'), 2)],
];

$recentSections = [
    [
        'title' => 'Last 5 Check-ins',
        'headers' => ['Name', 'Check-in Time'],
        'columns' => ['full_name', 'check_in_time'],
        'result' => mysqli_query(
            $conn,
            "SELECT users.full_name, DATE_FORMAT(attendance.check_in_time, '%M %d, %Y %h:%i %p') AS check_in_time
             FROM attendance
             INNER JOIN users ON attendance.user_id = users.id
             ORDER BY attendance.check_in_time DESC
             LIMIT 5"
        ),
    ],
    [
        'title' => 'Recent Payments',
        'headers' => ['Name', 'Amount', 'Payment Date'],
        'columns' => ['full_name', 'amount', 'payment_date'],
        'prefixes' => ['amount' => '₱'],
        'result' => mysqli_query(
            $conn,
            "SELECT users.full_name,
                    FORMAT(payments.amount, 2) AS amount,
                    DATE_FORMAT(payments.payment_date, '%M %d, %Y') AS payment_date
             FROM payments
             INNER JOIN users ON payments.user_id = users.id
             ORDER BY payments.payment_date DESC
             LIMIT 5"
        ),
    ],
    [
        'title' => 'New Registrations',
        'headers' => ['Name', 'Registration Date'],
        'columns' => ['full_name', 'created_at'],
        'result' => mysqli_query(
            $conn,
            "SELECT full_name,
                    DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS created_at
             FROM users
             WHERE role = 'member'
             ORDER BY created_at DESC
             LIMIT 5"
        ),
    ],
];

$navLinks = [
    'admin_dashboard.php' => ['Dashboard Overview', true, 'fas fa-tachometer-alt'],
    'members.php' => ['Members', false, 'fas fa-users'],
    'membership_plans.php' => ['Membership Plans', false, 'fas fa-cogs'],
    'attendance.php' => ['Attendance', false, 'fas fa-calendar-check'],
    'payments.php' => ['Payments', false, 'fas fa-credit-card'],
    'trainers.php' => ['Trainers/Staffs', false, 'fas fa-chalkboard-teacher'],
    'equipment_list.php' => ['Equipment List', false, 'fas fa-dumbbell'],
    'reports.php' => ['Reports', false, 'fas fa-file-alt'],
    '../index.php?logout=true' => ['Logout', false, 'fas fa-sign-out-alt'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vigor Gym Admin Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-image: url('bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            color: #fff;
            display: flex;
            min-height: 100vh;
        }

        .admin-dashboard {
            width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: row;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.55);
            border-radius: 10px;
            gap: 20px;
        }

        .admin-interface-title {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border-bottom: 2px solid #ca7bff;
        }

        .sidebar-title-container {
            padding: 20px 0;
            background: rgba(42, 26, 47, 0.88);
            text-align: center;
        }

        nav.sidebar {
            width: 250px;
            background: linear-gradient(180deg, rgba(42, 26, 47, 0.88) 0%, rgba(34, 5, 61, 0.88) 100%);
            padding: 20px;
            border-radius: 10px;
            margin-right: 0;
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(202, 123, 255, 0.15);
            flex-shrink: 0;
        }

        nav.sidebar ul {
            list-style: none;
            padding: 0;
        }

        nav.sidebar ul li {
            margin: 15px 0;
        }

        nav.sidebar ul li a {
            display: block;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            padding: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }

        nav.sidebar ul li a:hover {
            background-color: rgba(171, 82, 242, 0.28);
            color: #fff;
            border-left: 5px solid #ca7bff;
            padding-left: 15px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(171, 82, 242, 0.32);
            border-left-color: #ca7bff;
            padding-left: 15px;
        }

        .dashboard-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 0;
        }

        h1 {
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        }

        .overview {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .overview-item {
            flex: 1 1 calc(25% - 20px);
            min-width: 220px;
            background: rgba(44, 27, 83, 0.22);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(202, 123, 255, 0.20);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
            transition: all 0.3s ease;
        }

        .overview-item:hover {
            background: rgba(60, 30, 80, 0.28);
            transform: translateY(-2px);
        }

        .overview-item h3 {
            color: #e8c8ff;
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 600;
        }

        .overview-item p {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
        }

        .recent-activities {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .recent-activities .activity-card {
            flex: 1 1 calc(33.333% - 14px);
            min-width: 300px;
            background: rgba(44, 27, 83, 0.22);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(202, 123, 255, 0.20);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.25);
        }

        .recent-activities h4 {
            color: #e8c8ff;
            margin-bottom: 14px;
            font-size: 18px;
            font-weight: 700;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 10px;
            overflow: hidden;
        }

        table th,
        table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.16);
            font-size: 14px;
        }

        table th {
            background: rgba(124, 77, 255, 0.28);
            color: #f5eaff;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        table td {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            font-weight: 500;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
        }

        table tbody tr:hover td {
            background: rgba(202, 123, 255, 0.10);
            transition: 0.25s ease;
        }

        table td a {
            color: #f3b8ff;
            text-decoration: none;
        }

        @media (max-width: 1024px) {
            .recent-activities .activity-card {
                flex: 1 1 calc(50% - 10px);
            }
        }

        @media (max-width: 768px) {
            .admin-dashboard {
                flex-direction: column;
            }

            nav.sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }

            .overview-item {
                flex: 1 1 calc(50% - 10px);
            }

            .recent-activities .activity-card {
                flex: 1 1 100%;
            }
        }

        @media (max-width: 480px) {
            .overview-item {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
<div class="admin-dashboard">
    <nav class="sidebar">
        <div class="sidebar-title-container">
            <h2 class="admin-interface-title">Admin Interface</h2>
        </div>

        <ul>
            <?php
                $currentPage = basename($_SERVER['PHP_SELF']);
                foreach ($navLinks as $href => [$label, $active, $icon]):
                    $isActive = ($currentPage == basename($href)) ? 'active' : '';
            ?>
                <li>
                    <a href="<?= safe($href) ?>" class="<?= $isActive ?>">
                        <i class="<?= safe($icon) ?>"></i> <?= safe($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="dashboard-content">
        <h1>Admin Dashboard</h1>

      <div class="overview">
    <?php foreach ($stats as [$label, $value]): ?>
        <div class="overview-item">
            <h3><?= safe($label) ?></h3>
            <p><?= safe($value) ?></p>
        </div>
    <?php endforeach; ?>
</div>
    
        <div class="recent-activities">
            <?php foreach ($recentSections as $section): ?>
                <div class="activity-card">
                    <h4><?= safe($section['title']) ?></h4>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach ($section['headers'] as $header): ?>
                                        <th><?= safe($header) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php renderRows(
                                    $section['result'],
                                    $section['columns'],
                                    $section['prefixes'] ?? []
                                ); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>