<?php
session_start();
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
if (!$conn) {
    die("Database connection failed.");
}

mysqli_set_charset($conn, "utf8mb4");

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table)
{
    $table = mysqli_real_escape_string($conn, $table);
    $check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return ($check && mysqli_num_rows($check) > 0);
}

function columnExists($conn, $table, $column)
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($check && mysqli_num_rows($check) > 0);
}

function calculateExpirationDate($memberSince, $membershipPlan)
{
    if (empty($memberSince)) {
        return '';
    }

    $membershipPlan = strtolower(trim((string)$membershipPlan));
    $baseDate = strtotime($memberSince);

    if (!$baseDate) {
        return '';
    }

    if (strpos($membershipPlan, 'month') !== false) {
        return date('Y-m-d', strtotime('+1 month', $baseDate));
    }

    if (strpos($membershipPlan, 'quarter') !== false) {
        return date('Y-m-d', strtotime('+3 months', $baseDate));
    }

    if (strpos($membershipPlan, 'annual') !== false || strpos($membershipPlan, 'year') !== false) {
        return date('Y-m-d', strtotime('+1 year', $baseDate));
    }

    return '';
}

function getDaysRemaining($expirationDate)
{
    if (empty($expirationDate)) {
        return null;
    }

    $today = new DateTime(date('Y-m-d'));
    $expiry = DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime($expirationDate)));

    if (!$expiry) {
        return null;
    }

    $diff = $today->diff($expiry);
    $days = (int)$diff->days;

    return $expiry < $today ? -$days : $days;
}

function parseBenefits($benefitsText)
{
    $lines = preg_split('/\r\n|\r|\n/', (string)$benefitsText);
    $items = [];

    foreach ($lines as $line) {
        $line = trim($line);
        $line = preg_replace('/^[•\-\*]\s*/u', '', $line);

        if ($line !== '') {
            $items[] = $line;
        }
    }

    return $items;
}

function getStatusLabel($membershipStatus, $daysRemaining)
{
    if (strtolower((string)$membershipStatus) !== 'active') {
        return 'Inactive';
    }

    if ($daysRemaining === null) {
        return 'Active';
    }

    if ($daysRemaining < 0) {
        return 'Expired';
    }

    if ($daysRemaining <= 7) {
        return 'Expiring Soon';
    }

    return 'Active';
}

function queryValue($conn, $sql, $key, $default = 0)
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return $default;
    }

    $row = mysqli_fetch_assoc($result);
    return $row[$key] ?? $default;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {
    header("Location: index.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$hasExpirationDateColumn = columnExists($conn, 'users', 'expiration_date');

$userFields = "full_name, username, status, membership_plan, member_since, is_active";
if ($hasExpirationDateColumn) {
    $userFields .= ", expiration_date";
}

$userQuery = mysqli_query($conn, "
    SELECT $userFields
    FROM users
    WHERE id = $user_id
    LIMIT 1
");

if (!$userQuery || mysqli_num_rows($userQuery) === 0) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$user = mysqli_fetch_assoc($userQuery);

$user_name = $user['full_name'] ?? ($user['username'] ?? 'Member');
$membership_status = $user['status'] ?? 'Deactivated';
$membership_plan = $user['membership_plan'] ?? 'Standard';
$member_since = $user['member_since'] ?? '';
$is_active = (int)($user['is_active'] ?? 0);
$expiration_date = $hasExpirationDateColumn ? ($user['expiration_date'] ?? '') : '';

if ($is_active !== 1 || $membership_status !== 'Active') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

if (empty($expiration_date) && !empty($member_since) && !empty($membership_plan)) {
    $computedExpirationDate = calculateExpirationDate($member_since, $membership_plan);

    if ($computedExpirationDate !== '') {
        $expiration_date = $computedExpirationDate;

        if ($hasExpirationDateColumn) {
            $safeExpirationDate = mysqli_real_escape_string($conn, $computedExpirationDate);
            mysqli_query($conn, "
                UPDATE users
                SET expiration_date = '$safeExpirationDate'
                WHERE id = $user_id
            ");
        }
    }
}

$days_remaining = getDaysRemaining($expiration_date);
$membership_badge = getStatusLabel($membership_status, $days_remaining);

$plan_name_display = $membership_plan ?: 'Not set';
$plan_duration_display = 'Not set';
$plan_price_display = 'Not set';
$plan_benefits = [];
$plan_access_note = 'Membership details unavailable.';

if (tableExists($conn, 'membership_plans')) {
    $planKeyword = '';

    if (stripos($membership_plan, 'month') !== false) {
        $planKeyword = 'month';
    } elseif (stripos($membership_plan, 'quarter') !== false) {
        $planKeyword = 'quarter';
    } elseif (stripos($membership_plan, 'annual') !== false || stripos($membership_plan, 'year') !== false) {
        $planKeyword = 'annual';
    }

    $planSql = "
        SELECT plan_name, duration_months, duration_label, price, benefits
        FROM membership_plans
        WHERE is_active = 1
    ";

    if ($planKeyword !== '') {
        $safeKeyword = mysqli_real_escape_string($conn, $planKeyword);
        $planSql .= " AND plan_name LIKE '%$safeKeyword%'";
    } else {
        $safePlan = mysqli_real_escape_string($conn, $membership_plan);
        $planSql .= " AND plan_name = '$safePlan'";
    }

    $planSql .= " ORDER BY id ASC LIMIT 1";

    $planResult = mysqli_query($conn, $planSql);

    if ($planResult && mysqli_num_rows($planResult) > 0) {
        $planRow = mysqli_fetch_assoc($planResult);

        $plan_name_display = $planRow['plan_name'] ?? $plan_name_display;
        $plan_duration_display = $planRow['duration_label'] ?? 'Not set';
        $plan_price_display = isset($planRow['price']) ? '₱' . number_format((float)$planRow['price'], 2) : 'Not set';
        $plan_benefits = parseBenefits($planRow['benefits'] ?? '');
        $plan_access_note = !empty($plan_benefits) ? 'Benefits included in your current membership.' : 'No benefits listed for this plan.';
    }
}

/* PAYMENT INFO - MATCHED TO payments.php */
$payment_history = [];
$latest_payment_id = 'Not available';
$latest_payment_amount = 'Not available';
$latest_payment_date = 'No payment record';
$latest_payment_method = 'Not available';
$total_paid_display = '₱0.00';

if (tableExists($conn, 'payments') && columnExists($conn, 'payments', 'user_id')) {
    $paymentHistoryQuery = "
        SELECT payments.id, payments.amount, payments.payment_date, payments.payment_method
        FROM payments
        WHERE payments.user_id = $user_id
        ORDER BY payments.payment_date DESC
        LIMIT 20
    ";

    $paymentResult = mysqli_query($conn, $paymentHistoryQuery);

    if ($paymentResult) {
        while ($row = mysqli_fetch_assoc($paymentResult)) {
            $payment_history[] = $row;
        }
    }

    if (!empty($payment_history)) {
        $latest = $payment_history[0];

        $latest_payment_id = $latest['id'] ?? 'Not available';
        $latest_payment_amount = isset($latest['amount'])
            ? '₱' . number_format((float)$latest['amount'], 2)
            : 'Not available';
        $latest_payment_date = !empty($latest['payment_date'])
            ? date('F j, Y g:i A', strtotime($latest['payment_date']))
            : 'No payment record';
        $latest_payment_method = $latest['payment_method'] ?? 'Not available';
    }

    $total_paid = queryValue(
        $conn,
        "SELECT COALESCE(SUM(amount), 0) AS total_paid FROM payments WHERE user_id = $user_id",
        'total_paid',
        0
    );

    $total_paid_display = '₱' . number_format((float)$total_paid, 2);
}

$renewal_note = 'Renewal date unavailable.';
if (!empty($expiration_date)) {
    if ($days_remaining === null) {
        $renewal_note = 'Renewal date unavailable.';
    } elseif ($days_remaining < 0) {
        $renewal_note = 'Your membership has already expired.';
    } elseif ($days_remaining === 0) {
        $renewal_note = 'Your membership expires today.';
    } else {
        $renewal_note = 'Your next renewal is due on ' . date('F j, Y', strtotime($expiration_date)) . '.';
    }
}

$navLinks = [
    'user_dashboard.php' => ['Dashboard Overview', false, 'fas fa-home'],
    'user_attendance.php' => ['Attendance', false, 'fas fa-calendar-check'],
    'user_membership.php' => ['Membership', true, 'fas fa-id-card'],
    'user_presets.php' => ['Workout Presets', false, 'fas fa-dumbbell'],
    '../logout.php?logout=true' => ['Logout', false, 'fas fa-sign-out-alt'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Membership</title>

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
            border-radius: 12px;
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
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
            gap: 20px;
            min-width: 0;
        }

        h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        }

        .welcome-line {
            color: #f3e7ff;
            font-size: 16px;
            margin-top: -4px;
        }

        .overview {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .overview-item,
        .detail-card,
        .history-card {
            background: rgba(92, 61, 176, 0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(202, 123, 255, 0.25);
            border-radius: 14px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
        }

        .overview-item {
            padding: 20px;
            width: calc(33.333% - 10px);
            text-align: center;
        }

        .overview-item h3,
        .detail-card h3,
        .history-card h3 {
            color: #e9c8ff;
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 600;
        }

        .overview-item p,
        .overview-item li,
        .detail-card p,
        .detail-card li,
        .history-card p,
        .history-card td,
        .history-card th {
            color: #fff;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
        }

        .big-text {
            font-size: 26px !important;
            font-weight: 700 !important;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
        }

        .status-active {
            background: rgba(34, 197, 94, 0.18);
            border: 1px solid rgba(34, 197, 94, 0.35);
        }

        .status-warning {
            background: rgba(250, 204, 21, 0.18);
            border: 1px solid rgba(250, 204, 21, 0.35);
        }

        .status-expired {
            background: rgba(239, 68, 68, 0.18);
            border: 1px solid rgba(239, 68, 68, 0.35);
        }

        .details-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
            gap: 22px;
            align-items: stretch;
        }

        .detail-card {
            position: relative;
            padding: 0;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(92, 61, 176, 0.24) 0%, rgba(45, 20, 84, 0.30) 100%);
            border: 1px solid rgba(202, 123, 255, 0.22);
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.24);
        }

        .detail-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(202, 123, 255, 0.14), transparent 45%);
            pointer-events: none;
        }

        .detail-card-body {
            position: relative;
            z-index: 1;
            padding: 24px;
        }

        .detail-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        }

        .detail-card-title-wrap h3 {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 700;
            color: #f3d8ff;
        }

        .detail-card-subtitle {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
            color: #eadcff;
            opacity: 0.9;
        }

        .detail-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #f3d8ff;
            flex-shrink: 0;
            font-size: 16px;
        }

        .plan-meta-grid,
        .payment-highlight-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .meta-box {
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.10);
        }

        .meta-label {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #d9baff;
        }

        .meta-value {
            display: block;
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.4;
        }

        .meta-value.amount {
            font-size: 22px;
        }

        .access-note {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(202, 123, 255, 0.10);
            border: 1px solid rgba(202, 123, 255, 0.18);
            color: #f6ecff;
            line-height: 1.6;
            font-size: 14px;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }

        .benefits-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            line-height: 1.6;
            font-size: 14px;
        }

        .benefits-list li::before {
            content: "\f00c";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            color: #d9a7ff;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .info-stack {
            display: grid;
            gap: 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin: 0;
            padding: 13px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            line-height: 1.6;
            font-size: 14px;
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-label {
            color: #e3c6ff;
            font-weight: 600;
            flex: 0 0 auto;
        }

        .info-value {
            color: #ffffff;
            text-align: right;
            max-width: 65%;
            word-break: break-word;
        }

        .renewal-box {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: rgba(123, 97, 255, 0.10);
            border: 1px solid rgba(202, 123, 255, 0.16);
        }

        .renewal-box .meta-label {
            margin-bottom: 4px;
        }

        .history-card {
            padding: 20px;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(44, 27, 83, 0.14);
            border-radius: 12px;
            overflow: hidden;
        }

        table th,
        table td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            font-size: 14px;
        }

        table th {
            background: rgba(124, 77, 255, 0.28);
            color: #f5eaff;
            font-weight: 700;
        }

        table td {
            background: rgba(255, 255, 255, 0.05);
        }

        .empty-note {
            font-size: 14px;
            color: #eadcff;
            margin-top: 6px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px dashed rgba(255, 255, 255, 0.12);
        }

        @media (max-width: 1100px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .overview-item {
                width: calc(50% - 10px);
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
                width: 100%;
            }

            .plan-meta-grid,
            .payment-highlight-grid {
                grid-template-columns: 1fr;
            }

            .detail-card-body {
                padding: 20px;
            }

            .info-row {
                flex-direction: column;
                gap: 6px;
            }

            .info-value {
                max-width: 100%;
                text-align: left;
            }
        }

        /* Payment history table refinement */
        .table-wrap { overflow-x: auto; border: 1px solid rgba(202, 123, 255, .22); border-radius: 14px; background: rgba(25, 8, 45, .28); }
        table { width: 100%; border-collapse: separate; border-spacing: 0; overflow: hidden; }
        table th { padding: 16px 14px; background: linear-gradient(90deg, rgba(124, 44, 244, .48), rgba(171, 82, 242, .30)); color: #fff; font-size: 13px; font-weight: 700; letter-spacing: .35px; text-transform: uppercase; border-bottom: 1px solid rgba(226, 165, 255, .35); }
        table td { padding: 15px 14px; color: #f7efff; font-size: 14px; border-bottom: 1px solid rgba(255, 255, 255, .09); background: rgba(255, 255, 255, .045); }
        table tbody tr:nth-child(even) td { background: rgba(171, 82, 242, .075); }
        table tbody tr:hover td { background: rgba(171, 82, 242, .17); transition: background .2s ease; }
        table tbody tr:last-child td { border-bottom: 0; }
        

        /* VIGOR GYM - CONSISTENT USER INTERFACE */
        .sidebar-title-container {
            padding: 20px 12px 18px;
            margin-bottom: 18px;
            background: linear-gradient(135deg, rgba(85, 35, 125, 0.78), rgba(35, 10, 55, 0.96));
            text-align: center;
            border: 1px solid rgba(202, 123, 255, 0.25);
            border-radius: 12px;
        }
        .sidebar-brand-name { color: #fff; font-size: 23px; font-weight: 800; letter-spacing: 2px; line-height: 1.2; }
        .sidebar-brand-name span { color: #d9a5ff; }
        .sidebar-brand-subtitle { margin-top: 6px; color: #eadcff; font-size: 10px; font-weight: 700; letter-spacing: 3px; }
        .sidebar-profile { display: flex; align-items: center; gap: 12px; padding: 12px; margin-bottom: 20px; background: rgba(171, 82, 242, 0.13); border: 1px solid rgba(202, 123, 255, 0.25); border-radius: 12px; }
        .profile-icon { width: 42px; height: 42px; min-width: 42px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: linear-gradient(135deg, #d18aff, #8734c5); color: #fff; font-size: 18px; }
        .profile-info { display: flex; flex-direction: column; min-width: 0; }
        .profile-info strong { color: #fff; font-size: 13px; font-weight: 700; overflow-wrap: anywhere; }
        .profile-info span { color: #d9baff; font-size: 11px; margin-top: 3px; }
        nav.sidebar ul li { margin: 10px 0; }
        nav.sidebar ul li a { display: flex; align-items: center; gap: 13px; min-height: 48px; padding: 12px 13px; border-radius: 9px; border-left: 5px solid transparent; color: #fff; font-size: 14px; font-weight: 600; text-decoration: none; transition: background .25s ease, color .25s ease, padding .25s ease, box-shadow .25s ease; }
        nav.sidebar ul li a i { width: 22px; min-width: 22px; text-align: center; color: #d9a5ff; font-size: 16px; }
        nav.sidebar ul li a span { line-height: 1.4; }
        nav.sidebar ul li a:hover { background: rgba(171, 82, 242, .22); border-left-color: #ca7bff; padding-left: 18px; color: #f0caff; box-shadow: 0 3px 12px rgba(171, 82, 242, .12); }
        nav.sidebar ul li a.active { background: linear-gradient(90deg, rgba(171, 82, 242, .45), rgba(115, 45, 160, .20)); border-left-color: #e2a5ff; color: #fff; font-weight: 700; box-shadow: 0 0 12px rgba(190, 100, 255, .18); }
        .page-heading { position: relative; padding: 4px 0 16px; margin-bottom: 2px; }
        .page-title { position: relative; display: inline-block; margin: 0 0 8px; color: #fff; font-size: 36px; font-weight: 800; line-height: 1.2; letter-spacing: -.5px; text-shadow: 0 2px 8px rgba(0, 0, 0, .35); }
        .page-title::after { content: ""; display: block; width: 76px; height: 4px; margin-top: 10px; border-radius: 99px; background: linear-gradient(90deg, #e2a5ff, #8734c5); }
        .welcome-line { color: #eadcff; font-size: 14px; margin-top: 2px; }
        @media (max-width: 768px) { nav.sidebar { width: 100%; height: auto; position: relative; top: 0; } .page-title { font-size: 29px; } }
    </style>
</head>
<body>

<div class="admin-dashboard">
    <nav class="sidebar">
        <div class="sidebar-title-container">
            <div class="sidebar-brand-name">VIGOR <span>GYM</span></div>
            <div class="sidebar-brand-subtitle">MEMBER PORTAL</div>
        </div>

        <div class="sidebar-profile">
            <div class="profile-icon"><i class="fas fa-user"></i></div>
            <div class="profile-info">
                <strong><?= h($user_name) ?></strong>
                <span>Member</span>
            </div>
        </div>

        <ul>
            <?php foreach ($navLinks as $href => [$label, $active, $icon]): ?>
                <li>
                    <a href="<?= h($href) ?>" class="<?= $active ? 'active' : '' ?>">
                        <i class="<?= h($icon) ?>"></i>
                        <span><?= h($label) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="dashboard-content">
        <div class="page-heading">
            <h1 class="page-title">Membership</h1>
            <div class="welcome-line">Welcome back, <?= h($user_name) ?>!</div>
        </div>

        <div class="overview">
            <div class="overview-item">
                <h3>Current Plan</h3>
                <p class="big-text"><?= h($plan_name_display) ?></p>
            </div>

            <div class="overview-item">
                <h3>Status</h3>
                <p>
                    <span class="status-badge
                        <?= $membership_badge === 'Active' ? 'status-active' : '' ?>
                        <?= $membership_badge === 'Expiring Soon' ? 'status-warning' : '' ?>
                        <?= in_array($membership_badge, ['Expired', 'Inactive'], true) ? 'status-expired' : '' ?>">
                        <?= h($membership_badge) ?>
                    </span>
                </p>
            </div>

            <div class="overview-item">
                <h3>Member Since</h3>
                <p><?= $member_since ? h(date('F j, Y', strtotime($member_since))) : 'Not set' ?></p>
            </div>

            <div class="overview-item">
                <h3>Expiration Date</h3>
                <p><?= $expiration_date ? h(date('F j, Y', strtotime($expiration_date))) : 'Not set' ?></p>
            </div>

            <div class="overview-item">
                <h3>Days Remaining</h3>
                <p class="big-text">
                    <?php
                    if ($days_remaining === null) {
                        echo '—';
                    } elseif ($days_remaining < 0) {
                        echo 'Expired';
                    } else {
                        echo (int)$days_remaining;
                    }
                    ?>
                </p>
            </div>

            <div class="overview-item">
                <h3>Latest Payment</h3>
                <p><?= h($latest_payment_amount) ?></p>
                <p class="empty-note"><?= h($latest_payment_date) ?></p>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-card">
                <div class="detail-card-body">
                    <div class="detail-card-header">
                        <div class="detail-card-title-wrap">
                            <h3>Plan Benefits</h3>
                            <p class="detail-card-subtitle">Your membership inclusions are organized below for easier scanning.</p>
                        </div>
                        <div class="detail-icon">
                            <i class="fas fa-gem"></i>
                        </div>
                    </div>

                    <div class="plan-meta-grid">
                        <div class="meta-box">
                            <span class="meta-label">Duration</span>
                            <span class="meta-value"><?= h($plan_duration_display) ?></span>
                        </div>

                        <div class="meta-box">
                            <span class="meta-label">Price</span>
                            <span class="meta-value"><?= h($plan_price_display) ?></span>
                        </div>
                    </div>

                    <div class="access-note">
                        <?= h($plan_access_note) ?>
                    </div>

                    <?php if (!empty($plan_benefits)): ?>
                        <ul class="benefits-list">
                            <?php foreach ($plan_benefits as $benefit): ?>
                                <li><?= h($benefit) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="empty-note">No benefits listed for this membership.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-body">
                    <div class="detail-card-header">
                        <div class="detail-card-title-wrap">
                            <h3>Payment & Renewal</h3>
                            <p class="detail-card-subtitle">A cleaner summary of your latest transaction and membership renewal details.</p>
                        </div>
                        <div class="detail-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>

                    <div class="payment-highlight-grid">
                        <div class="meta-box">
                            <span class="meta-label">Latest Payment</span>
                            <span class="meta-value amount"><?= h($latest_payment_amount) ?></span>
                        </div>

                        <div class="meta-box">
                            <span class="meta-label">Total Paid</span>
                            <span class="meta-value amount"><?= h($total_paid_display) ?></span>
                        </div>
                    </div>

                    <div class="info-stack">
                        <div class="info-row">
                            <span class="info-label">Payment ID</span>
                            <span class="info-value"><?= h($latest_payment_id) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Payment Date</span>
                            <span class="info-value"><?= h($latest_payment_date) ?></span>
                        </div>

                        <div class="info-row">
                            <span class="info-label">Paid Via</span>
                            <span class="info-value"><?= h($latest_payment_method) ?></span>
                        </div>
                    </div>

                    <div class="renewal-box">
                        <span class="meta-label">Renewal Note</span>
                        <span class="meta-value"><?= h($renewal_note) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="history-card">
            <h3>Payment History</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Paid Via</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payment_history)): ?>
                            <?php foreach ($payment_history as $payment): ?>
                                <tr>
                                    <td><?= h($payment['id'] ?? '') ?></td>
                                    <td>
                                        <?php
                                        if (isset($payment['amount'])) {
                                            echo '₱' . number_format((float)$payment['amount'], 2);
                                        } else {
                                            echo 'Not available';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($payment['payment_date'])) {
                                            echo h(date('F j, Y g:i A', strtotime($payment['payment_date'])));
                                        } else {
                                            echo 'Not available';
                                        }
                                        ?>
                                    </td>
                                    <td><?= h($payment['payment_method'] ?? 'Not available') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No payment records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>