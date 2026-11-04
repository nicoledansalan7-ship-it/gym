<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$sessionRole = strtolower(trim($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $sessionRole !== 'admin') {
    header("Location: index.php?page=login");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
if (!$conn) {
    die("Database connection failed.");
}

mysqli_set_charset($conn, "utf8mb4");

$msg = "";
$type = "";
$showModal = false;

$planId = 0;
$planName = "";
$durationMonths = 1;
$durationLabel = "1 Month";
$price = "";
$benefits = "";

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function clean($conn, $value)
{
    return mysqli_real_escape_string($conn, trim((string)$value));
}

function durationLabelFromMonths($months)
{
    $months = (int)$months;
    if ($months === 1) return "1 Month";
    if ($months === 3) return "3 Months";
    if ($months === 12) return "12 Months";
    return $months . " Months";
}

function defaultBenefits($months)
{
    if ((int)$months === 1) {
        return "Unlimited gym access\nUse of basic equipment\nLocker access";
    }

    if ((int)$months === 3) {
        return "Unlimited gym access\nUse of basic equipment\nLocker access\nFree fitness assessment\n1 free personal training session\nPriority class scheduling";
    }

    if ((int)$months === 12) {
        return "Unlimited gym access\nUse of all equipment\nLocker access\nFree fitness assessment every quarter\n3 free personal training sessions\nPriority class scheduling\nFree gym shirt\nGuest pass once every 3 months\nDiscount on supplements and merchandise";
    }

    return "";
}

function benefitsToArray($benefitsText)
{
    $lines = preg_split('/\r\n|\r|\n/', (string)$benefitsText);
    $cleaned = [];

    foreach ($lines as $line) {
        $line = trim($line);
        $line = preg_replace('/^[•\-\*]\s*/u', '', $line);

        if ($line !== '') {
            $cleaned[] = $line;
        }
    }

    return $cleaned;
}

function columnExists($conn, $table, $column)
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($check && mysqli_num_rows($check) > 0);
}

function ensureMembershipPlansTable($conn)
{
    $createTable = "
        CREATE TABLE IF NOT EXISTS membership_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_name VARCHAR(150) NOT NULL,
            duration_months INT NOT NULL DEFAULT 1,
            duration_label VARCHAR(50) NOT NULL DEFAULT '1 Month',
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            benefits TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    mysqli_query($conn, $createTable);

    $columns = [
        'duration_months' => "ALTER TABLE membership_plans ADD COLUMN duration_months INT NOT NULL DEFAULT 1",
        'duration_label' => "ALTER TABLE membership_plans ADD COLUMN duration_label VARCHAR(50) NOT NULL DEFAULT '1 Month'",
        'is_active' => "ALTER TABLE membership_plans ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        'created_at' => "ALTER TABLE membership_plans ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columns as $column => $query) {
        if (!columnExists($conn, 'membership_plans', $column)) {
            mysqli_query($conn, $query);
        }
    }
}

ensureMembershipPlansTable($conn);

/* ADD OR UPDATE PLAN */
if (isset($_POST['save_plan'])) {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $planName = trim($_POST['plan_name'] ?? '');
    $durationMonths = (int)($_POST['duration_months'] ?? 1);
    $priceInput = trim($_POST['price'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $showModal = true;

    $priceNumeric = preg_replace('/[^0-9.]/', '', $priceInput);
    $priceValue = is_numeric($priceNumeric) ? (float)$priceNumeric : 0;
    $durationLabel = durationLabelFromMonths($durationMonths);

    if ($planName === '' || $benefits === '' || $priceInput === '') {
        $msg = "Please complete all fields.";
        $type = "error";
    } elseif (!in_array($durationMonths, [1, 3, 12], true)) {
        $msg = "Invalid duration selected.";
        $type = "error";
    } elseif ($priceValue <= 0) {
        $msg = "Please enter a valid price.";
        $type = "error";
    } else {
        $planNameSafe = clean($conn, $planName);
        $durationLabelSafe = clean($conn, $durationLabel);
        $benefitsSafe = clean($conn, $benefits);

        if ($planId > 0) {
            $update = mysqli_query(
                $conn,
                "UPDATE membership_plans
                 SET plan_name = '$planNameSafe',
                     duration_months = $durationMonths,
                     duration_label = '$durationLabelSafe',
                     price = $priceValue,
                     benefits = '$benefitsSafe'
                 WHERE id = $planId"
            );

            if ($update) {
                $msg = "Membership plan updated successfully.";
                $type = "success";
                $showModal = false;
                $planId = 0;
                $planName = "";
                $durationMonths = 1;
                $durationLabel = "1 Month";
                $price = "";
                $benefits = "";
            } else {
                $msg = "Unable to update membership plan.";
                $type = "error";
            }
        } else {
            $insert = mysqli_query(
                $conn,
                "INSERT INTO membership_plans (plan_name, duration_months, duration_label, price, benefits, is_active)
                 VALUES ('$planNameSafe', $durationMonths, '$durationLabelSafe', $priceValue, '$benefitsSafe', 1)"
            );

            if ($insert) {
                $msg = "New membership plan added successfully.";
                $type = "success";
                $showModal = false;
                $planId = 0;
                $planName = "";
                $durationMonths = 1;
                $durationLabel = "1 Month";
                $price = "";
                $benefits = "";
            } else {
                $msg = "Unable to add membership plan.";
                $type = "error";
            }
        }
    }
}

/* LOAD PLAN FOR EDIT */
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    if ($editId > 0) {
        $editQuery = mysqli_query($conn, "SELECT * FROM membership_plans WHERE id = $editId LIMIT 1");

        if ($editQuery && mysqli_num_rows($editQuery) > 0) {
            $editPlan = mysqli_fetch_assoc($editQuery);

            $planId = (int)($editPlan['id'] ?? 0);
            $planName = $editPlan['plan_name'] ?? '';
            $durationMonths = (int)($editPlan['duration_months'] ?? 1);
            $durationLabel = $editPlan['duration_label'] ?? durationLabelFromMonths($durationMonths);
            $price = isset($editPlan['price']) ? number_format((float)$editPlan['price'], 2, '.', '') : '';
            $benefits = $editPlan['benefits'] ?? '';
            $showModal = true;
        }
    }
}

$plansResult = mysqli_query(
    $conn,
    "SELECT id, plan_name, duration_months, duration_label, price, benefits, is_active, created_at
     FROM membership_plans
     ORDER BY id ASC"
);

$planRows = [];
if ($plansResult) {
    while ($row = mysqli_fetch_assoc($plansResult)) {
        $planRows[] = $row;
    }
}

$navLinks = [
    'admin_dashboard.php' => ['Dashboard Overview', false, 'fas fa-tachometer-alt'],
    'members.php' => ['Members', false, 'fas fa-users'],
    'membership_plans.php' => ['Membership Plans', true, 'fas fa-cogs'],
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
    <title>Membership Plans</title>

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
            height: 100vh;
        }

        .admin-dashboard {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: row;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.55);
            border-radius: 10px;
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
            border-radius: 12px;
            margin-right: 20px;
            position: sticky;
            top: 20px;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
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
            border-radius: 6px;
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
        }

        nav.sidebar ul li a:hover,
        .sidebar a.active {
            background: rgba(171, 82, 242, 0.32);
            border-left-color: #ca7bff;
            padding-left: 15px;
        }

        .dashboard-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
        }

        h1 {
            text-align: left;
            margin-bottom: 6px;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        }

        .msg {
            padding: 12px 16px;
            font-size: 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
            background: rgba(255,255,255,0.03);
            margin-bottom: 8px;
        }

        .msg.success {
            color: #f5deff;
            box-shadow: 0 0 10px rgba(194, 97, 255, 0.08);
        }

        .msg.error {
            color: #ffb0d2;
            box-shadow: 0 0 10px rgba(255, 120, 180, 0.06);
        }

        .table-container {
            margin-top: 14px;
            width: 100%;
            display: flex;
            justify-content: center;
            overflow-x: auto;
            border-radius: 12px;
        }

        table {
            width: 88%;
            max-width: 1100px;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
            background: rgba(44, 27, 83, 0.14);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 12px;
            overflow: hidden;
        }

        table th,
        table td {
            padding: 16px 12px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
        }

        table th {
            background: rgba(124, 77, 255, 0.28);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #f5eaff;
        }

        table td {
            background: rgba(255, 255, 255, 0.06);
            font-size: 16px;
            font-weight: 500;
            line-height: 1.6;
            color: #ffffff;
            vertical-align: middle;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
        }

        table tbody tr:hover td {
            background: rgba(202, 123, 255, 0.12);
            transition: 0.25s ease;
        }

        th:nth-child(1),
        td:nth-child(1) {
            width: 22%;
        }

        th:nth-child(2),
        td:nth-child(2) {
            width: 14%;
        }

        th:nth-child(3),
        td:nth-child(3) {
            width: 14%;
        }

        th:nth-child(4),
        td:nth-child(4) {
            width: 38%;
        }

        th:nth-child(5),
        td:nth-child(5) {
            width: 12%;
        }

        .plan-name {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
        }

        .plan-duration {
            font-size: 17px;
            font-weight: 600;
            color: #f5d0fe;
            white-space: nowrap;
        }

        .plan-price {
            font-size: 20px;
            font-weight: 700;
            color: #e9d5ff;
            white-space: nowrap;
        }

        .benefits-cell {
            text-align: left;
            vertical-align: top;
            padding-left: 22px;
        }

        .benefits-list {
            margin: 0;
            padding-left: 20px;
            text-align: left;
            list-style-position: outside;
            font-size: 15px;
            font-weight: 400;
            line-height: 1.9;
            color: #f8f5ff;
        }

        .benefits-list li {
            margin-bottom: 4px;
        }

        .actions-cell {
            width: 120px;
        }

        .edit-btn {
            background: rgba(124, 77, 255, 0.25);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(202, 123, 255, 0.25);
            padding: 8px 15px;
            color: white;
            cursor: pointer;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .edit-btn:hover {
            background: rgba(171, 82, 242, 0.32);
            transform: translateY(-1px);
        }

        .modal {
            display: <?php echo $showModal ? 'block' : 'none'; ?>;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
            padding-top: 40px;
        }

        .modal-content {
            position: relative;
            background: rgba(44, 27, 83, 0.88);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(202, 123, 255, 0.25);
            margin: 3% auto;
            padding: 35px;
            border-radius: 12px;
            width: 60%;
            max-width: 700px;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        .modal-content h2 {
            margin-bottom: 20px;
            font-size: 26px;
            font-weight: 700;
        }

        .close {
            color: #cfcfcf;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 12px;
            right: 20px;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #ff6b6b;
            text-decoration: none;
        }

        .form-input {
            margin-bottom: 15px;
            padding: 12px 14px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid rgba(202, 123, 255, 0.25);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            outline: none;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.75);
        }

        select.form-input option {
            background: #2C1B53;
            color: #fff;
        }

        textarea.form-input {
            min-height: 150px;
            resize: vertical;
        }

        .form-submit {
            background: rgba(124, 77, 255, 0.25);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(202, 123, 255, 0.25);
            color: white;
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            width: 100%;
            font-family: 'Poppins', sans-serif;
        }

        .form-submit:hover {
            background: rgba(171, 82, 242, 0.32);
        }

        @media (max-width: 992px) {
            .modal-content {
                width: 90%;
            }

            table {
                width: 100%;
            }

            .plan-name,
            .plan-duration,
            .plan-price {
                white-space: normal;
            }

            .benefits-cell {
                padding-left: 14px;
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

    <div class="dashboard-content">
        <h1>Membership Plans</h1>

        <?php if ($msg !== ""): ?>
            <div class="msg <?= h($type) ?>"><?= h($msg) ?></div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Benefits</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($planRows)): ?>
                    <tr>
                        <td colspan="5">No membership plans found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($planRows as $row): ?>
                        <?php $benefitItems = benefitsToArray($row['benefits'] ?? ''); ?>
                        <tr>
                            <td class="plan-name"><?= h($row['plan_name'] ?? '') ?></td>
                            <td class="plan-duration"><?= h($row['duration_label'] ?? durationLabelFromMonths($row['duration_months'] ?? 1)) ?></td>
                            <td class="plan-price">₱<?= number_format((float)($row['price'] ?? 0), 2) ?></td>
                            <td class="benefits-cell">
                                <ul class="benefits-list">
                                    <?php foreach ($benefitItems as $item): ?>
                                        <li><?= h($item) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="actions-cell">
                                <a class="edit-btn" href="membership_plans.php?edit=<?= (int)$row['id'] ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            </table>
        </div>

        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2 id="modalTitle"><?= $planId > 0 ? 'Edit Membership Plan' : 'New Membership Plan' ?></h2>

                <form id="planForm" action="" method="POST">
                    <input type="hidden" name="plan_id" value="<?= (int)$planId ?>">

                    <input
                        class="form-input"
                        type="text"
                        name="plan_name"
                        placeholder="Plan Name"
                        value="<?= h($planName) ?>"
                        required
                    >

                    <select class="form-input" name="duration_months" id="duration_months" required>
                        <option value="1" <?= (int)$durationMonths === 1 ? 'selected' : '' ?>>1 Month</option>
                        <option value="3" <?= (int)$durationMonths === 3 ? 'selected' : '' ?>>3 Months</option>
                        <option value="12" <?= (int)$durationMonths === 12 ? 'selected' : '' ?>>12 Months</option>
                    </select>

                    <input
                        class="form-input"
                        type="text"
                        name="price"
                        placeholder="Price"
                        value="<?= h($price) ?>"
                        required
                    >

                    <textarea
                        class="form-input"
                        name="benefits"
                        id="benefits"
                        placeholder="Benefits"
                        required
                    ><?= h($benefits !== '' ? $benefits : defaultBenefits($durationMonths)) ?></textarea>

                    <input
                        id="modalSubmit"
                        type="submit"
                        name="save_plan"
                        class="form-submit"
                        value="<?= $planId > 0 ? 'Update Plan' : 'Save Plan' ?>"
                    >
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function getBenefitsByDuration(months) {
        months = String(months);

        if (months === "1") {
            return [
                "Unlimited gym access",
                "Use of basic equipment",
                "Locker access"
            ];
        }

        if (months === "3") {
            return [
                "Unlimited gym access",
                "Use of basic equipment",
                "Locker access",
                "Free fitness assessment",
                "1 free personal training session",
                "Priority class scheduling"
            ];
        }

        if (months === "12") {
            return [
                "Unlimited gym access",
                "Use of all equipment",
                "Locker access",
                "Free fitness assessment every quarter",
                "3 free personal training sessions",
                "Priority class scheduling",
                "Free gym shirt",
                "Guest pass once every 3 months",
                "Discount on supplements and merchandise"
            ];
        }

        return [];
    }

    function formatBenefits(benefitsArray) {
        return benefitsArray.join("\n");
    }

    function closeModal() {
        document.getElementById("editModal").style.display = "none";
    }

    document.getElementById("duration_months").addEventListener("change", function () {
        const benefitsField = document.getElementById("benefits");
        const planId = document.querySelector('input[name="plan_id"]').value;

        if (planId === "0") {
            benefitsField.value = formatBenefits(getBenefitsByDuration(this.value));
        }
    });

    window.onclick = function (event) {
        const modal = document.getElementById("editModal");
        if (event.target === modal) {
            closeModal();
        }
    };
</script>

</body>
</html>