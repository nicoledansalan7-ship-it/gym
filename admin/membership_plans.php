<?php
session_start();

mysqli_report(MYSQLI_REPORT_OFF);

$sessionRole = strtolower(trim($_SESSION['role'] ?? ''));

if (!isset($_SESSION['user_id']) || $sessionRole !== 'admin') {
    header("Location: index.php?page=login");
    exit();
}

require_once dirname(__DIR__) . '/db.php';

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

/* =========================================================
   HELPER FUNCTIONS
   ========================================================= */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function durationLabelFromMonths($months)
{
    $months = (int)$months;

    if ($months === 1) {
        return "1 Month";
    }

    if ($months === 3) {
        return "3 Months";
    }

    if ($months === 12) {
        return "12 Months";
    }

    return $months . " Months";
}

function durationCategoryFromMonths($months)
{
    $months = (int)$months;

    if ($months === 1) {
        return "MONTHLY";
    }

    if ($months === 3) {
        return "QUARTERLY";
    }

    if ($months === 12) {
        return "YEARLY";
    }

    return strtoupper($months . " MONTHS");
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

    if (!mysqli_query($conn, $createTable)) {
        die("Unable to prepare membership plans table: " . h(mysqli_error($conn)));
    }

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

/* =========================================================
   ADD OR UPDATE MEMBERSHIP PLAN
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $planName = trim($_POST['plan_name'] ?? '');
    $durationMonths = (int)($_POST['duration_months'] ?? 1);
    $priceInput = trim($_POST['price'] ?? '');
    $benefits = trim($_POST['benefits'] ?? '');
    $showModal = true;

    // Allow users to enter prices with a peso sign or comma separators.
    $priceNumeric = preg_replace('/[^0-9.]/', '', $priceInput);
    $durationLabel = durationLabelFromMonths($durationMonths);

    if ($planName === '' || $benefits === '' || $priceInput === '') {
        $msg = "Please complete all fields.";
        $type = "error";
    } elseif (mb_strlen($planName) > 150) {
        $msg = "Plan name must not exceed 150 characters.";
        $type = "error";
    } elseif (!in_array($durationMonths, [1, 3, 12], true)) {
        $msg = "Invalid duration selected.";
        $type = "error";
    } elseif (!preg_match('/^\d+(?:\.\d{1,2})?$/', $priceNumeric) || (float)$priceNumeric <= 0) {
        $msg = "Please enter a valid price.";
        $type = "error";
    } else {
        $priceValue = (float)$priceNumeric;

        if ($planId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE membership_plans
                 SET plan_name = ?,
                     duration_months = ?,
                     duration_label = ?,
                     price = ?,
                     benefits = ?
                 WHERE id = ?"
            );

            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "sisdsi",
                    $planName,
                    $durationMonths,
                    $durationLabel,
                    $priceValue,
                    $benefits,
                    $planId
                );

                $saved = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            } else {
                $saved = false;
            }

            if ($saved) {
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
                $msg = "Unable to update membership plan. Please try again.";
                $type = "error";
            }
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO membership_plans
                    (plan_name, duration_months, duration_label, price, benefits, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)"
            );

            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "sisds",
                    $planName,
                    $durationMonths,
                    $durationLabel,
                    $priceValue,
                    $benefits
                );

                $saved = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            } else {
                $saved = false;
            }

            if ($saved) {
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
                $msg = "Unable to add membership plan. Please try again.";
                $type = "error";
            }
        }
    }

    if ($showModal) {
        $price = $priceInput;
    }
}

/* =========================================================
   LOAD PLAN FOR EDIT
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];

    if ($editId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, plan_name, duration_months, duration_label, price, benefits
             FROM membership_plans
             WHERE id = ?
             LIMIT 1"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $editId);
            mysqli_stmt_execute($stmt);
            $editQuery = mysqli_stmt_get_result($stmt);

            if ($editQuery && mysqli_num_rows($editQuery) > 0) {
                $editPlan = mysqli_fetch_assoc($editQuery);

                $planId = (int)($editPlan['id'] ?? 0);
                $planName = $editPlan['plan_name'] ?? '';
                $durationMonths = (int)($editPlan['duration_months'] ?? 1);
                $durationLabel = $editPlan['duration_label'] ?? durationLabelFromMonths($durationMonths);
                $price = isset($editPlan['price'])
                    ? number_format((float)$editPlan['price'], 2, '.', '')
                    : '';
                $benefits = $editPlan['benefits'] ?? '';
                $showModal = true;
            }

            mysqli_stmt_close($stmt);
        }
    }
}

/* =========================================================
   LOAD MEMBERSHIP PLANS AND SUMMARY VALUES
   ========================================================= */

$plansResult = mysqli_query(
    $conn,
    "SELECT id, plan_name, duration_months, duration_label, price, benefits, is_active, created_at
     FROM membership_plans
     ORDER BY duration_months ASC, id ASC"
);

$planRows = [];

if ($plansResult) {
    while ($row = mysqli_fetch_assoc($plansResult)) {
        $planRows[] = $row;
    }
}

$totalPlans = count($planRows);
$activePlans = 0;
$durationOptions = [];

foreach ($planRows as $row) {
    if ((int)($row['is_active'] ?? 1) === 1) {
        $activePlans++;
    }

    $durationOptions[(int)($row['duration_months'] ?? 1)] = true;
}

$totalDurationOptions = count($durationOptions);

$navLinks = [
    'admin_dashboard.php' => ['Dashboard Overview', 'fas fa-tachometer-alt'],
    'members.php' => ['Members', 'fas fa-users'],
    'membership_plans.php' => ['Membership Plans', 'fas fa-cogs'],
    'attendance.php' => ['Attendance', 'fas fa-calendar-check'],
    'payments.php' => ['Payments', 'fas fa-credit-card'],
    'trainers.php' => ['Trainers/Staffs', 'fas fa-chalkboard-teacher'],
    'equipment_list.php' => ['Equipment List', 'fas fa-dumbbell'],
    'reports.php' => ['Reports', 'fas fa-file-alt'],
    '../index.php?logout=true' => ['Logout', 'fas fa-sign-out-alt'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Plans | Vigor Gym Admin</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');

        :root {
            --vg-purple: #a245ff;
            --vg-purple-dark: #7c2cf4;
            --vg-purple-light: #ca7bff;
            --vg-purple-pale: #ead5ff;
            --vg-sidebar-top: rgba(42, 26, 47, 0.94);
            --vg-sidebar-bottom: rgba(34, 5, 61, 0.96);
            --vg-glass-border: rgba(202, 123, 255, 0.22);
            --vg-card-bg: rgba(44, 27, 83, 0.38);
            --vg-muted: #d8c6e8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            background-image: url('bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .admin-dashboard {
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            background: rgba(0, 0, 0, 0.55);
        }

        /* SIDEBAR — retained Vigor Gym admin navigation design */
        nav.sidebar {
            width: 260px;
            min-width: 260px;
            flex: 0 0 260px;
            height: calc(100vh - 32px);
            min-height: 600px;
            position: sticky;
            top: 16px;
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

        nav.sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        nav.sidebar ul li {
            margin: 10px 0;
        }

        nav.sidebar ul li a {
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

        nav.sidebar ul li a i {
            width: 24px;
            min-width: 24px;
            color: #d7a2ff;
            font-size: 19px;
            text-align: center;
        }

        nav.sidebar ul li a:hover,
        nav.sidebar ul li a.active {
            color: #fff;
            background: linear-gradient(90deg, rgba(171, 82, 242, 0.34), rgba(171, 82, 242, 0.10));
            border-left-color: #ca7bff;
            padding-left: 18px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.12);
        }

        nav.sidebar ul li a:hover i,
        nav.sidebar ul li a.active i {
            color: #f1d7ff;
        }

        /* MAIN CONTENT */
        .dashboard-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 22px;
            padding: 8px 4px 28px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            padding: 8px 2px 18px;
            border-bottom: 1px solid rgba(202, 123, 255, 0.24);
        }

        .page-heading {
            min-width: 240px;
        }

        .page-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: #d7a2ff;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .page-heading h1 {
            color: #fff;
            font-size: clamp(28px, 3vw, 38px);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: 0.2px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        }

        .page-heading p {
            margin-top: 8px;
            color: #e1d3ee;
            font-size: 14px;
            line-height: 1.6;
        }

        .primary-button {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 13px 20px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid rgba(230, 190, 255, 0.55);
            border-radius: 9px;
            background: linear-gradient(135deg, #a245ff, #7c2cf4);
            box-shadow: 0 7px 20px rgba(124, 44, 244, 0.30);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        .primary-button:hover {
            color: #fff;
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(124, 44, 244, 0.38);
        }

        /* SUMMARY CARDS */
        .plan-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .summary-card {
            min-height: 112px;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            border: 1px solid var(--vg-glass-border);
            border-radius: 12px;
            background: rgba(44, 27, 83, 0.36);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
        }

        .summary-icon {
            width: 54px;
            height: 54px;
            min-width: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            color: #fff;
            font-size: 22px;
            background: linear-gradient(145deg, rgba(202, 123, 255, 0.95), rgba(124, 44, 244, 0.90));
            box-shadow: 0 5px 14px rgba(124, 44, 244, 0.22);
        }

        .summary-info span {
            display: block;
            color: #d8c6e8;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .summary-info strong {
            display: block;
            margin-top: 5px;
            color: #fff;
            font-size: 27px;
            line-height: 1.1;
            font-weight: 800;
        }

        /* MEMBERSHIP PLAN CARDS */
        .section-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 2px;
        }

        .section-heading h2 {
            color: #fff;
            font-size: 21px;
            font-weight: 800;
        }

        .section-heading p {
            margin-top: 4px;
            color: #d8c6e8;
            font-size: 13px;
        }

        .plan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(285px, 1fr));
            align-items: stretch;
            gap: 20px;
        }

        .plan-card {
            position: relative;
            min-width: 0;
            min-height: 470px;
            display: flex;
            flex-direction: column;
            padding: 24px;
            overflow: hidden;
            border: 1px solid rgba(202, 123, 255, 0.30);
            border-radius: 16px;
            background:
                radial-gradient(circle at top right, rgba(162, 69, 255, 0.20), transparent 42%),
                linear-gradient(155deg, rgba(54, 29, 87, 0.88), rgba(29, 15, 49, 0.88));
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow: 0 10px 26px rgba(0, 0, 0, 0.24);
            transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .plan-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #7c2cf4, #ca7bff, #a245ff);
        }

        .plan-card:hover {
            transform: translateY(-5px);
            border-color: rgba(202, 123, 255, 0.62);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.32);
        }

        .plan-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 22px;
        }

        .duration-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 6px 11px;
            color: #f1ddff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.1px;
            border: 1px solid rgba(202, 123, 255, 0.42);
            border-radius: 30px;
            background: rgba(162, 69, 255, 0.18);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 27px;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .status-pill::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-pill.active {
            color: #a8f0c2;
            border: 1px solid rgba(94, 220, 145, 0.30);
            background: rgba(46, 160, 91, 0.14);
        }

        .status-pill.inactive {
            color: #ffc0d0;
            border: 1px solid rgba(255, 128, 164, 0.30);
            background: rgba(190, 55, 91, 0.14);
        }

        .plan-title {
            min-height: 72px;
        }

        .plan-title h3 {
            color: #fff;
            font-size: 23px;
            line-height: 1.25;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .plan-title p {
            margin-top: 7px;
            color: #d8c6e8;
            font-size: 13px;
        }

        .plan-price-block {
            margin: 20px 0 18px;
            padding: 18px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .price-caption {
            display: block;
            margin-bottom: 5px;
            color: #d8c6e8;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .price-line {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 4px;
        }

        .price-currency {
            color: #e8c8ff;
            font-size: 21px;
            font-weight: 700;
        }

        .price-amount {
            color: #fff;
            font-size: clamp(30px, 3vw, 38px);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.8px;
            overflow-wrap: anywhere;
        }

        .price-duration {
            margin-left: 4px;
            color: #d8c6e8;
            font-size: 12px;
            font-weight: 600;
        }

        .benefits-heading {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 12px;
            color: #f1ddff;
            font-size: 13px;
            font-weight: 800;
        }

        .benefits-heading i {
            color: #d7a2ff;
        }

        .benefits-list {
            flex: 1;
            margin: 0 0 22px;
            padding: 0;
            list-style: none;
        }

        .benefits-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 11px;
            color: #f5effb;
            font-size: 13px;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .benefits-list li i {
            margin-top: 3px;
            color: #c98bff;
            font-size: 13px;
            flex: 0 0 auto;
        }

        .no-benefits {
            color: #cdbbdc;
            font-size: 13px;
            font-style: italic;
        }

        .plan-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: auto;
            padding-top: 17px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .plan-duration-note {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #d8c6e8;
            font-size: 11px;
            font-weight: 600;
        }

        .plan-duration-note i {
            color: #d7a2ff;
        }

        .edit-btn {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 13px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
            border: 1px solid rgba(202, 123, 255, 0.42);
            border-radius: 8px;
            background: rgba(124, 77, 255, 0.25);
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .edit-btn:hover {
            color: #fff;
            background: rgba(162, 69, 255, 0.42);
            transform: translateY(-1px);
        }

        .empty-state {
            grid-column: 1 / -1;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 35px;
            text-align: center;
            border: 1px dashed rgba(202, 123, 255, 0.42);
            border-radius: 15px;
            background: rgba(44, 27, 83, 0.28);
        }

        .empty-state i {
            margin-bottom: 16px;
            color: #d7a2ff;
            font-size: 42px;
        }

        .empty-state h3 {
            color: #fff;
            font-size: 20px;
            font-weight: 800;
        }

        .empty-state p {
            max-width: 420px;
            margin: 8px 0 20px;
            color: #d8c6e8;
            font-size: 13px;
            line-height: 1.6;
        }

        /* ALERT MESSAGE */
        .msg {
            padding: 13px 16px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
        }

        .msg.success {
            color: #c5f7d5;
            border: 1px solid rgba(94, 220, 145, 0.28);
            background: rgba(46, 160, 91, 0.15);
        }

        .msg.error {
            color: #ffc5d5;
            border: 1px solid rgba(255, 128, 164, 0.28);
            background: rgba(190, 55, 91, 0.15);
        }

        /* ADD / EDIT MODAL */
        .modal {
            display: <?php echo $showModal ? 'flex' : 'none'; ?>;
            position: fixed;
            z-index: 1000;
            inset: 0;
            align-items: center;
            justify-content: center;
            padding: 22px;
            overflow-y: auto;
            background: rgba(8, 4, 15, 0.82);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }

        .modal-content {
            position: relative;
            width: min(100%, 650px);
            max-height: calc(100vh - 44px);
            overflow-y: auto;
            padding: 32px;
            color: #fff;
            border: 1px solid rgba(202, 123, 255, 0.38);
            border-radius: 15px;
            background: linear-gradient(145deg, rgba(54, 29, 87, 0.98), rgba(29, 15, 49, 0.98));
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.48);
        }

        .modal-heading {
            margin-bottom: 22px;
            padding-right: 35px;
        }

        .modal-heading span {
            display: block;
            margin-bottom: 6px;
            color: #d7a2ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .modal-heading h2 {
            color: #fff;
            font-size: 25px;
            font-weight: 800;
        }

        .modal-heading p {
            margin-top: 6px;
            color: #d8c6e8;
            font-size: 12px;
        }

        .close {
            position: absolute;
            top: 18px;
            right: 22px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e7d8f4;
            font-size: 22px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.06);
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .close:hover {
            color: #fff;
            background: rgba(202, 123, 255, 0.22);
        }

        .form-group {
            margin-bottom: 17px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: #f1ddff;
            font-size: 12px;
            font-weight: 700;
        }

        .form-input {
            width: 100%;
            min-height: 46px;
            padding: 12px 14px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            border: 1px solid rgba(202, 123, 255, 0.30);
            border-radius: 8px;
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-input:focus {
            border-color: #ca7bff;
            box-shadow: 0 0 0 3px rgba(162, 69, 255, 0.15);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.60);
        }

        select.form-input option {
            color: #fff;
            background: #2c1b53;
        }

        textarea.form-input {
            min-height: 155px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-help {
            display: block;
            margin-top: 6px;
            color: #cdbbdc;
            font-size: 11px;
            line-height: 1.5;
        }

        .form-submit {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            margin-top: 5px;
            padding: 12px 18px;
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 800;
            border: 1px solid rgba(230, 190, 255, 0.50);
            border-radius: 8px;
            background: linear-gradient(135deg, #a245ff, #7c2cf4);
            box-shadow: 0 6px 16px rgba(124, 44, 244, 0.25);
            cursor: pointer;
            transition: filter 0.2s ease, transform 0.2s ease;
        }

        .form-submit:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        @media (max-width: 1100px) {
            .plan-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .admin-dashboard {
                gap: 12px;
                padding: 12px;
            }

            nav.sidebar {
                width: 235px;
                min-width: 235px;
                flex-basis: 235px;
            }

            .plan-summary {
                gap: 10px;
            }

            .summary-card {
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .admin-dashboard {
                flex-direction: column;
                gap: 14px;
                padding: 12px;
            }

            nav.sidebar {
                position: relative;
                top: 0;
                width: 100%;
                min-width: 0;
                flex: 0 0 auto;
                height: auto;
                min-height: 0;
            }

            nav.sidebar ul {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 4px 7px;
            }

            nav.sidebar ul li {
                margin: 2px 0;
            }

            nav.sidebar ul li a {
                min-height: 48px;
                padding: 10px;
                font-size: 13px;
            }

            nav.sidebar ul li a i {
                font-size: 16px;
            }

            .dashboard-content {
                width: 100%;
                padding: 5px 0 20px;
            }

            .page-header {
                align-items: flex-start;
            }

            .plan-summary {
                grid-template-columns: 1fr;
            }

            .summary-card {
                min-height: 88px;
            }

            .plan-grid {
                grid-template-columns: 1fr;
            }

            .plan-card {
                min-height: 0;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .page-header .primary-button {
                width: 100%;
            }

            .plan-card {
                padding: 20px;
            }

            .modal {
                padding: 10px;
            }

            .modal-content {
                padding: 25px 19px;
                max-height: calc(100vh - 20px);
            }

            .modal-heading h2 {
                font-size: 21px;
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

                foreach ($navLinks as $href => [$label, $icon]):
                    $linkPath = parse_url($href, PHP_URL_PATH);
                    $isActive = ($currentPage === basename($linkPath)) ? 'active' : '';
            ?>
                <li>
                    <a href="<?= h($href) ?>" class="<?= $isActive ?>">
                        <i class="<?= h($icon) ?>"></i>
                        <span><?= h($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <main class="dashboard-content">

        <header class="page-header">
            <div class="page-heading">
                <span class="page-eyebrow">
                    <i class="fas fa-layer-group"></i>
                    Membership Management
                </span>
                <h1>Membership Plans</h1>
                <p>Manage your gym membership packages, pricing, and included benefits.</p>
            </div>

            <button type="button" class="primary-button" onclick="openAddModal()">
                <i class="fas fa-plus"></i>
                Add Membership Plan
            </button>
        </header>

        <?php if ($msg !== ""): ?>
            <div class="msg <?= h($type) ?>" role="alert">
                <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <section class="plan-summary" aria-label="Membership plan summary">
            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-layer-group"></i></div>
                <div class="summary-info">
                    <span>Total Plans</span>
                    <strong><?= (int)$totalPlans ?></strong>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-check-circle"></i></div>
                <div class="summary-info">
                    <span>Active Plans</span>
                    <strong><?= (int)$activePlans ?></strong>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="summary-info">
                    <span>Duration Options</span>
                    <strong><?= (int)$totalDurationOptions ?></strong>
                </div>
            </div>
        </section>

        <section class="section-heading">
            <div>
                <h2>Available Membership Packages</h2>
                <p>Compare each package's duration, price, and included benefits.</p>
            </div>
        </section>

        <section class="plan-grid" aria-label="Membership plans">
            <?php if (empty($planRows)): ?>
                <div class="empty-state">
                    <i class="fas fa-id-card"></i>
                    <h3>No Membership Plans Yet</h3>
                    <p>Create your first membership package to start organizing your gym's available plans.</p>
                    <button type="button" class="primary-button" onclick="openAddModal()">
                        <i class="fas fa-plus"></i>
                        Create First Plan
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($planRows as $row): ?>
                    <?php
                        $benefitItems = benefitsToArray($row['benefits'] ?? '');
                        $rowDuration = (int)($row['duration_months'] ?? 1);
                        $rowDurationLabel = $row['duration_label'] ?? durationLabelFromMonths($rowDuration);
                        $isActive = (int)($row['is_active'] ?? 1) === 1;
                    ?>

                    <article class="plan-card">
                        <div class="plan-card-top">
                            <span class="duration-badge">
                                <?= h(durationCategoryFromMonths($rowDuration)) ?>
                            </span>

                            <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>

                        <div class="plan-title">
                            <h3><?= h($row['plan_name'] ?? '') ?></h3>
                            <p><?= h($rowDurationLabel) ?> membership access</p>
                        </div>

                        <div class="plan-price-block">
                            <span class="price-caption">Membership Price</span>
                            <div class="price-line">
                                <span class="price-currency">₱</span>
                                <span class="price-amount"><?= number_format((float)($row['price'] ?? 0), 2) ?></span>
                                <span class="price-duration">/ <?= h($rowDurationLabel) ?></span>
                            </div>
                        </div>

                        <div class="benefits-heading">
                            <i class="fas fa-gift"></i>
                            Included Benefits
                        </div>

                        <?php if (!empty($benefitItems)): ?>
                            <ul class="benefits-list">
                                <?php foreach ($benefitItems as $item): ?>
                                    <li>
                                        <i class="fas fa-check-circle"></i>
                                        <span><?= h($item) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-benefits">No benefits listed for this plan.</p>
                        <?php endif; ?>

                        <div class="plan-card-footer">
                            <span class="plan-duration-note">
                                <i class="far fa-calendar-check"></i>
                                <?= h($rowDurationLabel) ?>
                            </span>

                            <a
                                class="edit-btn"
                                href="membership_plans.php?edit=<?= (int)$row['id'] ?>"
                                aria-label="Edit <?= h($row['plan_name'] ?? 'membership plan') ?>"
                            >
                                <i class="fas fa-pen"></i>
                                Edit Plan
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </main>
</div>

<!-- ADD / EDIT MEMBERSHIP PLAN MODAL -->
<div id="editModal" class="modal" aria-hidden="<?= $showModal ? 'false' : 'true' ?>">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button type="button" class="close" onclick="closeModal()" aria-label="Close modal">
            &times;
        </button>

        <div class="modal-heading">
            <span>Membership Package Details</span>
            <h2 id="modalTitle"><?= $planId > 0 ? 'Edit Membership Plan' : 'Create Membership Plan' ?></h2>
            <p>Enter the package name, duration, price, and benefits.</p>
        </div>

        <form id="planForm" action="membership_plans.php" method="POST">
            <input type="hidden" name="plan_id" value="<?= (int)$planId ?>">

            <div class="form-group">
                <label for="plan_name">Plan Name</label>
                <input
                    class="form-input"
                    type="text"
                    id="plan_name"
                    name="plan_name"
                    placeholder="e.g. Premium Membership"
                    value="<?= h($planName) ?>"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label for="duration_months">Membership Duration</label>
                <select class="form-input" name="duration_months" id="duration_months" required>
                    <option value="1" <?= (int)$durationMonths === 1 ? 'selected' : '' ?>>1 Month — Monthly</option>
                    <option value="3" <?= (int)$durationMonths === 3 ? 'selected' : '' ?>>3 Months — Quarterly</option>
                    <option value="12" <?= (int)$durationMonths === 12 ? 'selected' : '' ?>>12 Months — Yearly</option>
                </select>
            </div>

            <div class="form-group">
                <label for="price">Membership Price (₱)</label>
                <input
                    class="form-input"
                    type="text"
                    id="price"
                    name="price"
                    placeholder="e.g. 1500.00"
                    value="<?= h($price) ?>"
                    inputmode="decimal"
                    required
                >
                <small class="form-help">Enter the total price for the selected membership duration.</small>
            </div>

            <div class="form-group">
                <label for="benefits">Membership Benefits</label>
                <textarea
                    class="form-input"
                    name="benefits"
                    id="benefits"
                    placeholder="Enter one benefit per line"
                    required
                ><?= h($benefits !== '' ? $benefits : defaultBenefits($durationMonths)) ?></textarea>
                <small class="form-help">Enter each benefit on a separate line.</small>
            </div>

            <button id="modalSubmit" type="submit" name="save_plan" class="form-submit">
                <i class="fas fa-save"></i>
                <?= $planId > 0 ? 'Update Membership Plan' : 'Save Membership Plan' ?>
            </button>
        </form>
    </div>
</div>

<script>
    const editModal = document.getElementById("editModal");
    const planForm = document.getElementById("planForm");

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

    function openAddModal() {
        planForm.reset();

        document.querySelector('input[name="plan_id"]').value = "0";
        document.getElementById("plan_name").value = "";
        document.getElementById("duration_months").value = "1";
        document.getElementById("price").value = "";
        document.getElementById("benefits").value = getBenefitsByDuration("1").join("\n");

        document.getElementById("modalTitle").textContent = "Create Membership Plan";
        document.getElementById("modalSubmit").innerHTML = '<i class="fas fa-save"></i> Save Membership Plan';

        editModal.style.display = "flex";
        editModal.setAttribute("aria-hidden", "false");
        document.getElementById("plan_name").focus();
    }

    function closeModal() {
        editModal.style.display = "none";
        editModal.setAttribute("aria-hidden", "true");

        // Remove the edit query from the address bar after closing the form.
        if (window.location.search.indexOf("edit=") !== -1) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    document.getElementById("duration_months").addEventListener("change", function () {
        const planId = document.querySelector('input[name="plan_id"]').value;

        // Automatically suggest default benefits only when creating a new plan.
        if (planId === "0") {
            document.getElementById("benefits").value = getBenefitsByDuration(this.value).join("\n");
        }
    });

    editModal.addEventListener("click", function (event) {
        if (event.target === editModal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && editModal.style.display === "flex") {
            closeModal();
        }
    });
</script>

</body>
</html>