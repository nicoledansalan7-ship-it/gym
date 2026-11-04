<?php

session_start();



if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {

    header("Location: index.php?page=login");

    exit();

}



$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");

if (!$conn) {

    die("Database connection failed: " . mysqli_connect_error());

}



$msg = "";

$type = "";



if (isset($_POST['record_payment'])) {

    $member_name = trim($_POST['member_name'] ?? '');

    $payment_method = trim($_POST['payment_method'] ?? '');

    $amount = trim($_POST['amount'] ?? '');



    if ($member_name === "" || $payment_method === "" || $amount === "") {

        $msg = "Please complete all fields.";

        $type = "error";

    } elseif (!is_numeric($amount) || $amount <= 0) {

        $msg = "Please enter a valid amount.";

        $type = "error";

    } else {

        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE full_name = ? LIMIT 1");

        mysqli_stmt_bind_param($stmt, "s", $member_name);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $user = mysqli_fetch_assoc($result);



        if ($user) {

            $user_id = $user['id'];



            // Insert the payment into the payments table

            $insert = mysqli_prepare($conn, "INSERT INTO payments (user_id, amount, payment_method, payment_date) VALUES (?, ?, ?, NOW())");

            mysqli_stmt_bind_param($insert, "ids", $user_id, $amount, $payment_method);



                if (mysqli_stmt_execute($insert)) {

                $msg = "Payment recorded successfully.";

                $type = "success";



                // Clear the form fields so they appear empty

                $_POST['member_name'] = '';

                $_POST['payment_method'] = '';

                $_POST['amount'] = '';

            } else {

                $msg = "Failed to record payment: " . mysqli_error($conn);

                $type = "error";

            }

        }

    }

}



?>

<?php

$navLinks = [

    'admin_dashboard.php' => ['Dashboard Overview', true, 'fas fa-tachometer-alt'], // Dashboard

    'members.php' => ['Members', false, 'fas fa-users'], // Members

    'membership_plans.php' => ['Membership Plans', false, 'fas fa-cogs'], // Membership Plans

    'attendance.php' => ['Attendance', false, 'fas fa-calendar-check'], // Attendance

    'payments.php' => ['Payments', false, 'fas fa-credit-card'], // Payments

    'trainers.php' => ['Trainers/Staffs', false, 'fas fa-chalkboard-teacher'], // Trainers/Staffs

    'equipment_list.php' => ['Equipment List', false, 'fas fa-dumbbell'], // Equipment List

    'reports.php' => ['Reports', false, 'fas fa-file-alt'], // Reports

    '../index.php?logout=true' => ['Logout', false, 'fas fa-sign-out-alt'], // Logout

];

?>





<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payments - Vigor Gym</title>



    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {

            font-family: 'Poppins', sans-serif;

            background: url('bg.png') center/cover no-repeat;

            color: #fff;

            min-height: 100vh;

        }

        .admin-dashboard {

            min-height: 100vh;

            display: flex;

            gap: 20px;

            padding: 20px;

            background: rgba(0, 0, 0, .55);

        }



        .admin-interface-title {

            font-size: 24px;

            font-weight: 700;

            color: #fff;

            text-align: center;

            margin-bottom: 20px;  / Space between title and first navigation item /

            padding: 10px;

            border-bottom: 2px solid #ca7bff; / Optional: adds a border to separate title from links /

        }



        .sidebar-title-container {

            padding: 20px 0;  / Add padding to give space inside the sidebar /

            background: rgba(42, 26, 47, 0.88);  / Optional: make it match the sidebar's background /

            text-align: center;

        }



        .sidebar {

            width: 250px;

            padding: 20px;

            border-radius: 10px;

            flex-shrink: 0;

            position: sticky;

            top: 20px;

            height: calc(100vh - 40px);

            background: linear-gradient(180deg, rgba(42, 26, 47, .9), rgba(34, 5, 61, .9));

            box-shadow: 0 4px 8px rgba(0, 0, 0, .4);

            backdrop-filter: blur(8px);

            -webkit-backdrop-filter: blur(8px);

        }

        .sidebar ul { list-style: none; }

        .sidebar li { margin: 15px 0; }

        .sidebar a {

            display: block;

            padding: 10px;

            border-radius: 5px;

            color: #fff;

            text-decoration: none;

            font-size: 16px;

            font-weight: 600;

            border-left: 5px solid transparent;

            transition: .25s;

        }

        .sidebar a:hover, .sidebar a.active {

            background: rgba(171, 82, 242, .32);

            border-left-color: #ca7bff;

            padding-left: 15px;

        }

        .main {

            flex: 1;

            min-width: 0;

            display: flex;

            flex-direction: column;

            gap: 18px;

        }

        h1 {

            font-size: 36px;

            font-weight: 700;

            text-shadow: 0 2px 10px rgba(0, 0, 0, .35);

        }

        .glass {

            background: rgba(44, 27, 83, .22);

            border: 1px solid rgba(202, 123, 255, .18);

            border-radius: 12px;

            box-shadow: 0 6px 18px rgba(0, 0, 0, .2);

            backdrop-filter: blur(12px);

            -webkit-backdrop-filter: blur(12px);

        }

        .tabs, .panel { padding: 14px 16px; }

        .tabs {

            display: flex;

            gap: 14px;

            align-items: center;

            flex-wrap: wrap;

        }

        .tab, .btn, .action-btn {

            border: none;

            color: #fff;

            border-radius: 8px;

            font: 600 15px 'Poppins', sans-serif;

        }

        .tab {

            background: rgba(255, 255, 255, .03);

            padding: 11px 16px;

            cursor: pointer;

            transition: .25s;

        }

        .tab.active, .btn, .action-btn {

            background: linear-gradient(180deg, #a245ff, #7c2cf4);

            box-shadow: 0 4px 10px rgba(124, 44, 244, .35);

        }

        .tab:hover, .btn:hover, .action-btn:hover { opacity: .95; }

        .panel { display: none; }

        .panel.active { display: block; }

        .panel h3 {

            font-size: 18px;

            margin-bottom: 14px;

            color: #f3e7ff;

        }

        .row {

            display: flex;

            gap: 14px;

            flex-wrap: wrap;

            align-items: center;

        }

        .row + .row { margin-top: 14px; }

        label {

            font-size: 15px;

            font-weight: 500;

            color: #eddcff;

            min-width: 140px;

        }

        .input, select {

            flex: 1;

            min-width: 220px;

            padding: 12px 14px;

            border-radius: 8px;

            outline: none;

            border: 1px solid rgba(202, 123, 255, .18);

            background: rgba(30, 12, 53, .78);

            color: #fff;

            font: 500 15px 'Poppins', sans-serif;

        }

        input::placeholder { color: rgba(255, 255, 255, .65); }

        select { cursor: pointer; }

        .btn {

            padding: 11px 18px;

            cursor: pointer;

        }

        .table-wrap { overflow-x: auto; }

        table {

            width: 100%;

            border-collapse: collapse;

        }

        th, td {

            padding: 14px 16px;

            text-align: left;

            white-space: nowrap;

            border-bottom: 1px solid rgba(255, 255, 255, .08);

            height: 54px;

        }

        th {

            background: rgba(50, 21, 83, .55);

            color: #f4e9ff;

            font-size: 14px;

            font-weight: 700;

        }

        td {

            background: rgba(255, 255, 255, .03);

            font-size: 14px;

        }

        tbody tr:nth-child(even) td { background: rgba(255, 255, 255, .05); }

        tbody tr:hover td { background: rgba(202, 123, 255, .10); }

        .action-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 108px;

            padding: 8px 16px;

            text-decoration: none;

        }

        .action-btn.disabled {

            opacity: .8;

            pointer-events: none;

        }



        .msg {

            margin-bottom: 14px;

            padding: 12px 14px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 500;

        }

        .msg.success {

            background: rgba(80, 200, 120, 0.15);

            border: 1px solid rgba(80, 200, 120, 0.35);

            color: #d8ffe6;

        }

        .msg.error {

            background: rgba(255, 80, 120, 0.15);

            border: 1px solid rgba(255, 80, 120, 0.35);

            color: #ffd8e2;

        }

        .footer {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 12px;

            flex-wrap: wrap;

            padding: 14px 16px;

            color: #ead9ff;

            font-size: 14px;

            background: rgba(30, 12, 53, .55);

        }

        .pagination {

            display: flex;

            gap: 8px;

            align-items: center;

        }

        .page {

            min-width: 42px;

            height: 32px;

            padding: 0 12px;

            border-radius: 7px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            background: rgba(124, 44, 244, .28);

            border: 1px solid rgba(202, 123, 255, .16);

            font-weight: 600;

            color: #fff;

        }

        .page.active {

            background: linear-gradient(180deg, #a245ff, #7c2cf4);

        }

        @media (max-width: 768px) {

            .admin-dashboard { flex-direction: column; }

            .sidebar {

                width: 100%;

                height: auto;

                position: relative;

                top: 0;

            }

        }



/* =========================================================

   VIGOR GYM — ADMIN THEME MATCHED TO THE MEMBER PORTAL

   Visual layer only. Existing application logic is retained.

   ========================================================= */

:root {

    --vg-purple: #a245ff;

    --vg-purple-dark: #7c2cf4;

    --vg-purple-light: #ca7bff;

    --vg-sidebar-top: rgba(42, 26, 47, 0.94);

    --vg-sidebar-bottom: rgba(34, 5, 61, 0.96);

    --vg-glass-border: rgba(202, 123, 255, 0.22);

}



body {

    font-family: 'Poppins', sans-serif;

    background-image: url('bg.png');

    background-size: cover;

    background-position: center;

    background-repeat: no-repeat;

    color: #fff;

    min-height: 100vh;

}



.admin-dashboard {

    width: 100%;

    min-height: 100vh;

    display: flex;

    flex-direction: row;

    align-items: flex-start;

    gap: 16px;

    padding: 16px;

    background: rgba(0, 0, 0, 0.55);

    border-radius: 10px;

}



nav.sidebar,
.sidebar {
    width: 260px;
    min-width: 260px;
    flex: 0 0 260px;
    height: calc(100vh - 40px);
    min-height: 600px;
    position: sticky;
    top: 20px;
    padding: 20px;
    overflow-y: auto;
    border: 1px solid rgba(202, 123, 255, 0.12);
    border-radius: 12px;
    background: linear-gradient(180deg, var(--vg-sidebar-top) 0%, var(--vg-sidebar-bottom) 100%);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}



.sidebar-brand {
    min-height: 90px;
    margin: 0 0 12px;
    padding: 14px 10px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    border: 1px solid rgba(202, 123, 255, 0.42);
    border-radius: 9px;
    background: linear-gradient(135deg, rgba(119, 39, 180, 0.34), rgba(50, 15, 79, 0.50));
    box-shadow: inset 0 0 18px rgba(202, 123, 255, 0.06);
}



.sidebar-brand-name {
    color: #fff;
    font-size: 24px;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: 0.5px;
}



.sidebar-brand-name span {
    color: #d7a2ff;
}



.sidebar-brand-subtitle {
    margin-top: 5px;
    color: #eadcff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
}



.sidebar-profile {
    min-height: 72px;
    margin-bottom: 14px;
    padding: 11px 13px;
    display: flex;
    align-items: center;
    gap: 12px;
    border: 1px solid rgba(202, 123, 255, 0.30);
    border-radius: 9px;
    background: rgba(171, 82, 242, 0.16);
}



.sidebar-avatar {
    width: 44px;
    height: 44px;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #fff;
    font-size: 20px;
    background: linear-gradient(145deg, #e4a8ff, #9138d9);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.20);
}



.sidebar-profile-info {
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.25;
}



.sidebar-profile-name {
    overflow: hidden;
    color: #fff;
    font-size: 15px;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}



.sidebar-profile-role {
    margin-top: 3px;
    color: #eadcff;
    font-size: 13px;
    font-weight: 500;
}



nav.sidebar ul,
.sidebar ul {
    list-style: none;
    padding: 0;
    margin: 0;
}



nav.sidebar ul li,
.sidebar ul li {
    margin: 10px 0;
}



nav.sidebar ul li a,
.sidebar ul li a {
    min-height: 56px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 13px 15px;
    color: #fff;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
    border-left: 4px solid transparent;
    border-radius: 8px;
    transition: all 0.25s ease;
}



nav.sidebar ul li a i,
.sidebar ul li a i {
    width: 24px;
    min-width: 24px;
    color: #d7a2ff;
    font-size: 19px;
    text-align: center;
}



nav.sidebar ul li a:hover,
nav.sidebar ul li a.active,
.sidebar ul li a:hover,
.sidebar ul li a.active {
    color: #fff;
    background: linear-gradient(90deg, rgba(171, 82, 242, 0.34), rgba(171, 82, 242, 0.10));
    border-left-color: #ca7bff;
    padding-left: 18px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.12);
}



nav.sidebar ul li a:hover i,
nav.sidebar ul li a.active i,
.sidebar ul li a:hover i,
.sidebar ul li a.active i {
    color: #f1d7ff;
}



.main,

.dashboard-content {

    flex: 1;

    min-width: 0;

}



h1 {

    color: #fff;

    font-family: 'Poppins', sans-serif;

    font-size: 36px;

    font-weight: 800;

    line-height: 1.2;

    margin-bottom: 8px;

    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);

}



.glass,

.members-list,

.overview-item,

.details-card,

.report-card,

.card,

.panel,

.table-card,

.filter-card,

.summary-card {

    border-color: var(--vg-glass-border);

    backdrop-filter: blur(12px);

    -webkit-backdrop-filter: blur(12px);

}



@media (max-width: 768px) {

    .admin-dashboard {

        flex-direction: column;

        gap: 14px;

        padding: 12px;

    }



    nav.sidebar,

    .sidebar {

        position: relative;

        top: 0;

        width: 100%;

        min-width: 0;

        flex: 0 0 auto;

        height: auto;

        min-height: 0;

    }



    nav.sidebar ul,

    .sidebar ul {

        display: grid;

        grid-template-columns: repeat(2, minmax(0, 1fr));

        gap: 4px 7px;

    }



    nav.sidebar ul li,

    .sidebar ul li {

        margin: 2px 0;

    }



    h1 {

        font-size: 30px;

    }

}




/* Payments sidebar: match the working Attendance navigation exactly. */
body .admin-dashboard nav.sidebar ul li a {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 14px !important;
    min-height: 56px !important;
    padding: 13px 15px !important;
    text-align: left !important;
    font-family: 'Poppins', sans-serif !important;
    font-size: 16px !important;
    font-weight: 700 !important;
    line-height: 1.35 !important;
}

body .admin-dashboard nav.sidebar ul li a i {
    display: inline-block !important;
    flex: 0 0 24px !important;
    width: 24px !important;
    min-width: 24px !important;
    color: #d7a2ff !important;
    font-size: 19px !important;
    text-align: center !important;
}

body .admin-dashboard nav.sidebar ul li a span {
    display: inline-block !important;
    flex: 1 1 auto !important;
    text-align: left !important;
}

body .admin-dashboard nav.sidebar ul li a:hover,
body .admin-dashboard nav.sidebar ul li a.active {
    padding-left: 18px !important;
}

</style>

</head>

<body>

<div class="admin-dashboard">

    <nav class="sidebar">

    <div class="sidebar-brand">

        <div class="sidebar-brand-name">VIGOR <span>GYM</span></div>

        <div class="sidebar-brand-subtitle">ADMIN PORTAL</div>

    </div>



    <div class="sidebar-profile">

        <div class="sidebar-avatar"><i class="fas fa-user"></i></div>

        <div class="sidebar-profile-info">

            <span class="sidebar-profile-name">Administrator</span>

            <span class="sidebar-profile-role">Admin</span>

        </div>

    </div>



    <ul>

        <?php

            $currentPage = basename($_SERVER['PHP_SELF']);

            foreach ($navLinks as $href => [$label, $active, $icon]):

                $isActive = ($currentPage === basename(parse_url($href, PHP_URL_PATH))) ? 'active' : '';

        ?>

            <li>

                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= $isActive ?>">

                    <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>

                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>

                </a>

            </li>

        <?php endforeach; ?>

    </ul>

</nav>



    <main class="main">

        <h1>Payments</h1>



       <div class="glass tabs">

    <button type="button" class="tab active" data-target="record-payment">Record Payment</button>

    <button type="button" class="tab" data-target="payment-history">Payment History</button>

        </div>



        <div id="record-payment" class="glass panel active">

            <h3>Record Payment</h3>

            <?php if ($msg != ""): ?>

                <div class="msg <?php echo $type; ?>"><?php echo htmlspecialchars($msg); ?></div>

            <?php endif; ?>

            <form method="POST">

                <div class="row">

                    <label>Member Name</label>

                    <input class="input" type="text" name="member_name" placeholder="Enter member name"

                        value="<?php echo htmlspecialchars($_POST['member_name'] ?? ''); ?>" required>

                    <button class="btn" type="submit" name="record_payment">Record Payment</button>

                </div>



                <div class="row">

                    <label>Select Payment Method</label>

                    <select name="payment_method" required>

                        <option value="">Select Method</option>

                        <option value="credit_card" <?php echo (($_POST['payment_method'] ?? '') === 'credit_card') ? 'selected' : ''; ?>>Credit Card</option>

                        <option value="debit_card" <?php echo (($_POST['payment_method'] ?? '') === 'debit_card') ? 'selected' : ''; ?>>Debit Card</option>

                        <option value="paypal" <?php echo (($_POST['payment_method'] ?? '') === 'paypal') ? 'selected' : ''; ?>>PayPal</option>

                        <option value="bank_transfer" <?php echo (($_POST['payment_method'] ?? '') === 'bank_transfer') ? 'selected' : ''; ?>>Bank Transfer</option>

                        <option value="cash" <?php echo (($_POST['payment_method'] ?? '') === 'cash') ? 'selected' : ''; ?>>Cash</option>

                    </select>



                    <label style="min-width:70px">Amount</label>

                    <input class="input" type="text" name="amount" placeholder="Enter amount"

                        value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required>

                </div>

            </form>

        </div>



        <div id="payment-history" class="glass panel">

    <h3>Payment History</h3>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Payment ID</th>

                    <th>Member</th>

                    <th>Amount</th>

                    <th>Payment Date</th>

                    <th>Paid Via</th>

                </tr>

            </thead>

            <tbody>

                <?php

                $paymentHistoryQuery = "

                    SELECT payments.id, users.full_name, payments.amount, payments.payment_date, payments.payment_method

                    FROM payments

                    INNER JOIN users ON payments.user_id = users.id

                    ORDER BY payments.payment_date DESC

                    LIMIT 20

                ";

                $result = mysqli_query($conn, $paymentHistoryQuery);



                if ($result && mysqli_num_rows($result) > 0) {

                    while ($row = mysqli_fetch_assoc($result)) {

                        echo "<tr>";

                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";

                        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";

                        echo "<td>₱" . number_format((float)$row['amount'], 2) . "</td>";

                        echo "<td>" . htmlspecialchars($row['payment_date']) . "</td>";

                        echo "<td>" . htmlspecialchars($row['payment_method']) . "</td>";

                        echo "</tr>";

                    }

                } else {

                    echo "<tr><td colspan='5' style='text-align:center;'>No payment records found.</td></tr>";

                }

                ?>

            </tbody>

        </table>

    </div>

</div>



<script>

document.addEventListener('DOMContentLoaded', function () {

    const tabs = document.querySelectorAll('.tab');

    const panels = document.querySelectorAll('.panel');



    function activateTab(targetId) {

        tabs.forEach(tab => {

            tab.classList.toggle('active', tab.dataset.target === targetId);

        });



        panels.forEach(panel => {

            panel.classList.toggle('active', panel.id === targetId);

        });

    }



    tabs.forEach(tab => {

        tab.addEventListener('click', function () {

            activateTab(this.dataset.target);

        });

    });



    activateTab('record-payment');

});

</script>

</body>

</html>