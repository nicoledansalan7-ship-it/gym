<?php

session_start();

date_default_timezone_set('Asia/Manila');

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

$records_per_page = 8;

$page = max(1, (int)($_GET['page'] ?? 1));

$offset = ($page - 1) * $records_per_page;

$msg = "";

$type = "";

function h($value)

{

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}

function clean($conn, $text)

{

    return mysqli_real_escape_string($conn, trim((string)$text));

}

function columnExists($conn, $table, $column)

{

    $table = mysqli_real_escape_string($conn, $table);

    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    return ($check && mysqli_num_rows($check) > 0);

}

function firstExistingColumn($conn, $table, $columns)

{

    foreach ($columns as $column) {

        if (columnExists($conn, $table, $column)) {

            return $column;

        }

    }

    return "";

}

function formatTimeValue($value)

{

    if (!$value || $value === "0000-00-00 00:00:00") {

        return "--:--";

    }

    return date("F j, Y, g:i A", strtotime($value));

}

if (isset($_GET['attendance_added']) && $_GET['attendance_added'] == '1') {

    $msg = "Attendance recorded successfully!";

    $type = "success";

}

if (isset($_GET['attendance_exists']) && $_GET['attendance_exists'] == '1') {

    $msg = "Attendance for this member on this date already exists.";

    $type = "error";

}

$user_name_column = firstExistingColumn($conn, "users", ["full_name", "name", "username"]);

$attendance_status_column = firstExistingColumn($conn, "attendance", ["status", "attendance_status"]);

$check_in_column = firstExistingColumn($conn, "attendance", ["check_in_time", "checkin_time", "check_in", "time_in"]);

$check_out_column = firstExistingColumn($conn, "attendance", ["check_out_time", "checkout_time", "check_out", "time_out"]);

$membership_column = firstExistingColumn($conn, "users", ["membership_plan", "membership_type", "plan_name", "subscription_type", "membership"]);

$plan_tab = $_GET['plan_tab'] ?? "Monthly";

$allowed_tabs = ["Monthly", "Quarterly", "Yearly"];

if (!in_array($plan_tab, $allowed_tabs, true)) {

    $plan_tab = "Monthly";

}

$membership_filter = trim($_GET['membership_filter'] ?? "All Memberships");

$search = trim($_GET['search'] ?? "");
$selected_date = trim($_GET['attendance_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) || !checkdate((int)substr($selected_date, 5, 2), (int)substr($selected_date, 8, 2), (int)substr($selected_date, 0, 4))) {
    $selected_date = date('Y-m-d');
}

$membership_options = ["All Memberships"];

if ($membership_column !== "") {

    $membership_query = mysqli_query(

        $conn,

        "SELECT DISTINCT `$membership_column` AS membership_name

         FROM users

         WHERE `$membership_column` IS NOT NULL AND `$membership_column` <> ''

         ORDER BY `$membership_column` ASC"

    );

    if ($membership_query) {

        while ($membership_row = mysqli_fetch_assoc($membership_query)) {

            $membership_name = trim((string)($membership_row['membership_name'] ?? ''));

            if ($membership_name !== '') {

                $membership_options[] = $membership_name;

            }

        }

    }

}

$membership_options = array_values(array_unique($membership_options));

$select_name = $user_name_column !== "" ? "users.`$user_name_column`" : "'Unknown Member'";

$select_membership = $membership_column !== "" ? "users.`$membership_column`" : "'Membership Unavailable'";

$select_check_in = $check_in_column !== "" ? "attendance.`$check_in_column`" : "NULL";

$select_check_out = $check_out_column !== "" ? "attendance.`$check_out_column`" : "NULL";

$select_status = $attendance_status_column !== "" ? "attendance.`$attendance_status_column`" : "NULL";

$where_parts = [];

if ($check_in_column !== "") {

    $safe_date = clean($conn, $selected_date);

    $where_parts[] = "DATE(attendance.`$check_in_column`) = '$safe_date'";

}

if ($membership_column !== "") {

    if ($plan_tab === "Monthly") {

        $where_parts[] = "users.`$membership_column` LIKE '%Monthly%'";

    } elseif ($plan_tab === "Quarterly") {

        $where_parts[] = "users.`$membership_column` LIKE '%Quarterly%'";

    } elseif ($plan_tab === "Yearly") {

        $where_parts[] = "(users.`$membership_column` LIKE '%Yearly%' OR users.`$membership_column` LIKE '%Annual%')";

    }

    if ($membership_filter !== "All Memberships") {

        $safe_membership_filter = clean($conn, $membership_filter);

        $where_parts[] = "users.`$membership_column` = '$safe_membership_filter'";

    }

}

if ($search !== "") {

    $safe_search = clean($conn, $search);

    $search_conditions = [];

    if ($user_name_column !== "") {

        $search_conditions[] = "users.`$user_name_column` LIKE '%$safe_search%'";

    }

    if ($membership_column !== "") {

        $search_conditions[] = "users.`$membership_column` LIKE '%$safe_search%'";

    }

    if (!empty($search_conditions)) {

        $where_parts[] = "(" . implode(" OR ", $search_conditions) . ")";

    }

}

$where_sql = "";

if (!empty($where_parts)) {

    $where_sql = "WHERE " . implode(" AND ", $where_parts);

}

// Summary cards use the selected date and membership filters, but not the text search.
$summary_where_parts = [];
if ($check_in_column !== "") {
    $summary_where_parts[] = "DATE(attendance.`$check_in_column`) = '$selected_date'";
}
if ($membership_column !== "") {
    if ($plan_tab === "Monthly") {
        $summary_where_parts[] = "users.`$membership_column` LIKE '%Monthly%'";
    } elseif ($plan_tab === "Quarterly") {
        $summary_where_parts[] = "users.`$membership_column` LIKE '%Quarterly%'";
    } elseif ($plan_tab === "Yearly") {
        $summary_where_parts[] = "(users.`$membership_column` LIKE '%Yearly%' OR users.`$membership_column` LIKE '%Annual%')";
    }
    if ($membership_filter !== "All Memberships") {
        $safe_membership_filter = clean($conn, $membership_filter);
        $summary_where_parts[] = "users.`$membership_column` = '$safe_membership_filter'";
    }
}
$summary_where_sql = !empty($summary_where_parts) ? "WHERE " . implode(" AND ", $summary_where_parts) : "";
$summary_base_sql = "FROM attendance LEFT JOIN users ON attendance.user_id = users.id $summary_where_sql";
$total_checkins = (int) fetchAttendanceCount($conn, "SELECT COUNT(*) AS total $summary_base_sql");
$current_inside = 0;
$checked_out = 0;
if ($check_in_column !== "" && $check_out_column !== "") {
    $current_inside = (int) fetchAttendanceCount($conn, "SELECT COUNT(*) AS total $summary_base_sql" . ($summary_where_sql ? " AND" : " WHERE") . " (attendance.`$check_out_column` IS NULL OR attendance.`$check_out_column` = '' OR attendance.`$check_out_column` = '0000-00-00 00:00:00')");
    $checked_out = (int) fetchAttendanceCount($conn, "SELECT COUNT(*) AS total $summary_base_sql" . ($summary_where_sql ? " AND" : " WHERE") . " attendance.`$check_out_column` IS NOT NULL AND attendance.`$check_out_column` <> '' AND attendance.`$check_out_column` <> '0000-00-00 00:00:00'");
}
$late_arrivals = null;
if ($attendance_status_column !== "") {
    $late_arrivals = (int) fetchAttendanceCount($conn, "SELECT COUNT(*) AS total $summary_base_sql" . ($summary_where_sql ? " AND" : " WHERE") . " LOWER(COALESCE(attendance.`$attendance_status_column`, '')) LIKE '%late%'");
}

function fetchAttendanceCount($conn, $sql)
{
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return (int)($row['total'] ?? 0);
}

$order_by = "attendance.id DESC";

if ($check_in_column !== "") {

    $order_by = "attendance.`$check_in_column` DESC, attendance.id DESC";

}

$attendance_rows = [];

$attendance_sql = "

    SELECT

        attendance.id,

        attendance.user_id,

        $select_name AS full_name,

        $select_membership AS membership_name,

        $select_check_in AS check_in_time,

        $select_check_out AS check_out_time,

        $select_status AS attendance_status

    FROM attendance

    LEFT JOIN users ON attendance.user_id = users.id

    $where_sql

    ORDER BY $order_by

    LIMIT $records_per_page OFFSET $offset

";

$attendance_result = mysqli_query($conn, $attendance_sql);

if ($attendance_result) {

    while ($row = mysqli_fetch_assoc($attendance_result)) {

        $attendance_rows[] = $row;

    }

}

$total_entries_sql = "

    SELECT COUNT(*) AS total_entries

    FROM attendance

    LEFT JOIN users ON attendance.user_id = users.id

    $where_sql

";

$total_entries_result = mysqli_query($conn, $total_entries_sql);

$total_entries_row = $total_entries_result ? mysqli_fetch_assoc($total_entries_result) : ['total_entries' => 0];

$total_entries = (int)($total_entries_row['total_entries'] ?? 0);

$total_pages = max(1, (int)ceil($total_entries / $records_per_page));

if ($page > $total_pages) {

    $page = $total_pages;

    $offset = ($page - 1) * $records_per_page;

}

$showing_start = $total_entries > 0 ? $offset + 1 : 0;

$showing_end = min($offset + $records_per_page, $total_entries);

function buildAttendanceUrl($pageNumber, $plan_tab, $membership_filter, $search, $selected_date)

{

    $params = [

        'page' => $pageNumber,

        'plan_tab' => $plan_tab,
        'attendance_date' => $selected_date

    ];

    if ($membership_filter !== "All Memberships") {

        $params['membership_filter'] = $membership_filter;

    }

    if ($search !== "") {

        $params['search'] = $search;

    }

    return 'attendance.php?' . http_build_query($params);

}

$navLinks = [

    'admin_dashboard.php' => ['Dashboard Overview', false, 'fas fa-tachometer-alt'],

    'members.php' => ['Members', false, 'fas fa-users'],

    'membership_plans.php' => ['Membership Plans', false, 'fas fa-cogs'],

    'attendance.php' => ['Attendance', true, 'fas fa-calendar-check'],

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

    <title>Attendance - Vigor Gym</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

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

            min-height: 100vh;

        }

        .admin-dashboard {

            width: 100%;

            min-height: 100vh;

            display: flex;

            flex-direction: row;

            padding: 20px;

            gap: 20px;

            background-color: rgba(0, 0, 0, 0.55);

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

            background: linear-gradient(180deg, rgba(42, 26, 47, 0.90) 0%, rgba(34, 5, 61, 0.90) 100%);

            padding: 20px;

            border-radius: 10px;

            position: sticky;

            top: 20px;

            height: calc(100vh - 40px);

            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.40);

            backdrop-filter: blur(8px);

            -webkit-backdrop-filter: blur(8px);

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

        nav.sidebar ul li a*:hover*,

        nav.sidebar ul li a.active {

            background-color: rgba(171, 82, 242, 0.32);

            color: #fff;

            border-left: 5px solid #ca7bff;

            padding-left: 15px;

        }

        .main-content {

            flex: 1;

            min-width: 0;

            display: flex;

            flex-direction: column;

            gap: 18px;

            padding-top: 4px;

        }

        .page-title {

            font-size: 48px;

            font-weight: 700;

            line-height: 1;

            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);

            margin-bottom: 4px;

        }

        .top-bar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 16px;

            flex-wrap: wrap;

            background: rgba(44, 27, 83, 0.22);

            backdrop-filter: blur(12px);

            -webkit-backdrop-filter: blur(12px);

            border: 1px solid rgba(202, 123, 255, 0.18);

            padding: 10px 14px;

            border-radius: 12px;

            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.20);

        }

        .attendance-tabs {

            display: flex;

            gap: 12px;

            flex-wrap: wrap;

        }

        .attendance-tab {

            text-decoration: none;

            color: #e8d6ff;

            font-weight: 600;

            font-size: 15px;

            padding: 10px 18px;

            border-radius: 8px;

            background: rgba(255, 255, 255, 0.03);

            transition: all 0.25s ease;

        }

        .attendance-tab:hover,

        .attendance-tab.active {

            background: linear-gradient(180deg, #a245ff 0%, #7c2cf4 100%);

            color: #fff;

            box-shadow: 0 4px 10px rgba(124, 44, 244, 0.35);

        }

        .history-btn {

            text-decoration: none;

            color: #fff;

            font-weight: 600;

            font-size: 15px;

            padding: 12px 22px;

            border-radius: 8px;

            background: linear-gradient(180deg, #8d38ff 0%, #6326c7 100%);

            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);

            transition: transform 0.2s ease, opacity 0.2s ease;

        }

        .history-btn:hover {

            transform: translateY(-1px);

            opacity: 0.95;

        }

        .filter-bar {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 14px;

            flex-wrap: wrap;

            background: rgba(44, 27, 83, 0.22);

            backdrop-filter: blur(12px);

            -webkit-backdrop-filter: blur(12px);

            border: 1px solid rgba(202, 123, 255, 0.18);

            padding: 16px 18px;

            border-radius: 12px;

            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.20);

        }

        .filter-left {

            display: flex;

            align-items: center;

            gap: 18px;

            flex-wrap: wrap;

        }

        .filter-title {

            font-size: 18px;

            font-weight: 600;

            color: #f3e7ff;

            min-width: 200px;

        }

        .filter-form {

            display: flex;

            align-items: center;

            gap: 14px;

            flex-wrap: wrap;

            margin-left: auto;

        }

        .select-wrap {

            position: relative;

        }

        .select-wrap select,

        .search-input {

            font-family: 'Poppins', sans-serif;

            font-size: 15px;

            color: #fff;

            background: rgba(30, 12, 53, 0.78);

            border: 1px solid rgba(202, 123, 255, 0.18);

            border-radius: 8px;

            padding: 12px 16px;

            outline: none;

        }

        .select-wrap select {

            min-width: 250px;

            appearance: none;

            -webkit-appearance: none;

            -moz-appearance: none;

            padding-right: 44px;

            cursor: pointer;

        }

        .select-wrap::after {

            content: "▼";

            position: absolute;

            right: 14px;

            top: 50%;

            transform: translateY(-50%);

            font-size: 12px;

            color: #c99bff;

            pointer-events: none;

        }

        .search-input {

            min-width: 220px;

        }

        .msg {

            margin: 2px 0 0;

            padding: 12px 15px;

            border-radius: 8px;

            font-weight: 500;

            backdrop-filter: blur(8px);

            -webkit-backdrop-filter: blur(8px);

        }

        .msg.success {

            background-color: rgba(23, 122, 58, 0.20);

            color: #dff7e7;

            border: 1px solid rgba(183, 228, 199, 0.25);

        }

        .msg.error {

            background-color: rgba(180, 35, 24, 0.20);

            color: #ffe2e0;

            border: 1px solid rgba(245, 194, 199, 0.25);

        }

        .table-card {

            background: rgba(44, 27, 83, 0.22);

            backdrop-filter: blur(12px);

            -webkit-backdrop-filter: blur(12px);

            border: 1px solid rgba(202, 123, 255, 0.18);

            border-radius: 12px;

            overflow: hidden;

            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.20);

        }

        .table-wrap {

            overflow-x: auto;

        }

        table {

            width: 100%;

            border-collapse: collapse;

        }

        table th,

        table td {

            padding: 16px 18px;

            text-align: left;

            border-bottom: 1px solid rgba(255, 255, 255, 0.08);

            white-space: nowrap;

        }

        table th {

            font-size: 14px;

            font-weight: 700;

            color: #f4e9ff;

            background: rgba(50, 21, 83, 0.55);

            text-transform: none;

        }

        table td {

            font-size: 14px;

            font-weight: 500;

            color: #fff;

            background: rgba(255, 255, 255, 0.03);

        }

        table tbody tr*:nth-child*(even) td {

            background: rgba(255, 255, 255, 0.05);

        }

        table tbody tr*:hover* td {

            background: rgba(202, 123, 255, 0.10);

        }

        .view-btn {

            display: inline-block;

            padding: 9px 20px;

            border-radius: 7px;

            background: linear-gradient(180deg, #a245ff 0%, #7c2cf4 100%);

            color: #fff;

            text-decoration: none;

            font-weight: 600;

            font-size: 14px;

            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.22);

        }

        .view-btn:hover {

            opacity: 0.95;

        }

        .table-footer {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 12px;

            flex-wrap: wrap;

            padding: 14px 18px;

            background: rgba(30, 12, 53, 0.55);

            color: #ead9ff;

            font-size: 14px;

        }

        .pagination {

            display: flex;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;

        }

        .page-btn,

        .page-number {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            min-width: 42px;

            height: 32px;

            padding: 0 12px;

            border-radius: 7px;

            text-decoration: none;

            font-weight: 600;

            color: #fff;

            background: rgba(124, 44, 244, 0.28);

            border: 1px solid rgba(202, 123, 255, 0.16);

        }

        .page-number.active {

            background: linear-gradient(180deg, #a245ff 0%, #7c2cf4 100%);

        }

        .empty-state {

            padding: 28px 18px;

            color: #f3e7ff;

            font-size: 15px;

        }

        .attendance-heading { display:flex; align-items:center; gap:18px; padding:24px 26px; border:1px solid rgba(202,123,255,.28); border-radius:16px; background:linear-gradient(120deg,rgba(92,35,145,.72),rgba(35,15,62,.72)); box-shadow:0 10px 28px rgba(0,0,0,.24); }
        .attendance-heading-icon { width:64px; height:64px; flex:0 0 64px; display:grid; place-items:center; border-radius:16px; color:#fff; font-size:29px; background:linear-gradient(145deg,#b45cff,#7628d7); box-shadow:0 8px 20px rgba(124,44,244,.35); }
        .attendance-heading h1 { font-size:32px; line-height:1.2; margin:0 0 5px; }
        .attendance-heading p { color:#e5d2fa; font-size:14px; }
        .summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; }
        .summary-card { position:relative; overflow:hidden; min-height:132px; padding:20px 22px; border:1px solid rgba(202,123,255,.24); border-radius:14px; background:linear-gradient(145deg,rgba(61,32,95,.78),rgba(29,15,49,.78)); box-shadow:0 8px 22px rgba(0,0,0,.20); }
        .summary-card:after { content:""; position:absolute; width:105px; height:105px; right:-28px; top:-28px; border-radius:50%; background:rgba(202,123,255,.10); }
        .summary-top { display:flex; justify-content:space-between; align-items:center; gap:12px; color:#e8d7fa; font-size:13px; font-weight:600; }
        .summary-icon { width:38px; height:38px; display:grid; place-items:center; border-radius:11px; background:rgba(162,69,255,.22); color:#e6c5ff; font-size:17px; }
        .summary-value { display:block; margin-top:12px; color:#fff; font-size:32px; line-height:1; font-weight:800; }
        .summary-caption { display:block; margin-top:7px; color:#cbb5df; font-size:11px; }
        .filter-heading { display:flex; align-items:center; gap:10px; color:#f3e7ff; font-size:17px; font-weight:700; }
        .filter-form { width:100%; }
        .filter-form input[type=date] { color-scheme:dark; min-width:170px; }
        .filter-form button { border:0; cursor:pointer; font-family:inherit; font-weight:700; color:#fff; padding:12px 19px; border-radius:8px; background:linear-gradient(180deg,#a245ff,#7c2cf4); }
        .filter-form .reset-link { color:#e8d6ff; text-decoration:none; padding:10px 4px; font-size:13px; }
        .table-heading { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:19px 20px; border-bottom:1px solid rgba(202,123,255,.18); }
        .table-heading h2 { font-size:18px; color:#fff; }
        .table-heading span { color:#d6c1ed; font-size:12px; }
        .member-cell { display:flex; align-items:center; gap:12px; }
        .member-avatar { width:38px; height:38px; flex:0 0 38px; display:grid; place-items:center; border-radius:50%; background:linear-gradient(145deg,#d19aff,#8031d0); color:#fff; font-weight:800; }
        .member-name { font-weight:700; color:#fff; }
        .membership-chip { display:inline-block; padding:6px 11px; border-radius:20px; color:#e8d5ff; background:rgba(162,69,255,.16); border:1px solid rgba(202,123,255,.22); font-size:12px; font-weight:600; }
        .attendance-status { display:inline-flex; align-items:center; gap:7px; padding:7px 11px; border-radius:20px; font-size:12px; font-weight:700; }
        .attendance-status:before { content:""; width:7px; height:7px; border-radius:50%; background:currentColor; }
        .attendance-status.inside { color:#a8f0c1; background:rgba(37,160,87,.16); }
        .attendance-status.completed { color:#cbb6ff; background:rgba(124,77,255,.18); }
        .attendance-status.late { color:#ffd18b; background:rgba(224,145,30,.17); }
        .attendance-status.unknown { color:#d8cce6; background:rgba(255,255,255,.08); }
        .table-card table th { position:sticky; top:0; z-index:1; }
        .table-card table tbody tr { height:68px; }
        .table-footer { border-top:1px solid rgba(202,123,255,.16); }
        @media(max-width:768px) { .attendance-heading { padding:18px; } .attendance-heading-icon { width:52px;height:52px;flex-basis:52px;font-size:23px; } .attendance-heading h1 { font-size:25px; } .filter-form { display:grid; grid-template-columns:1fr; } .filter-form input,.filter-form button { width:100%; } .table-heading { align-items:flex-start; flex-direction:column; } }

        @media (max-width: 1024px) {

            .page-title {

                font-size: 38px;

            }

            .filter-form {

                margin-left: 0;

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

            .page-title {

                font-size: 32px;

            }

            .top-bar,

            .filter-bar {

                padding: 14px;

            }

            .select-wrap select,

            .search-input {

                min-width: 100%;

                width: 100%;

            }

            .filter-form {

                width: 100%;

            }

        }

/* =========================================================

   VIGOR GYM — ADMIN THEME MATCHED TO THE MEMBER PORTAL

   Visual layer only. Existing application logic is retained.

   ========================================================= */

*:root* {

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

nav.sidebar ul li a*:hover*,

nav.sidebar ul li a.active,

.sidebar ul li a*:hover*,

.sidebar ul li a.active {

    color: #fff;

    background: linear-gradient(90deg, rgba(171, 82, 242, 0.34), rgba(171, 82, 242, 0.10));

    border-left-color: #ca7bff;

    padding-left: 18px;

    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.12);

}

nav.sidebar ul li a*:hover* i,

nav.sidebar ul li a.active i,

.sidebar ul li a*:hover* i,

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

    <div class="main-content">

        <section class="attendance-heading">
            <div class="attendance-heading-icon"><i class="fas fa-calendar-check"></i></div>
            <div>
                <h1>Attendance Monitoring</h1>
                <p>Monitor daily check-ins, active members, and completed visits.</p>
            </div>
        </section>

        <div class="top-bar">
            <div class="attendance-tabs">
                <a class="attendance-tab <?= $plan_tab === 'Monthly' ? 'active' : '' ?>" href="attendance.php?plan_tab=Monthly&amp;attendance_date=<?= h($selected_date) ?>">Monthly</a>
                <a class="attendance-tab <?= $plan_tab === 'Quarterly' ? 'active' : '' ?>" href="attendance.php?plan_tab=Quarterly&amp;attendance_date=<?= h($selected_date) ?>">Quarterly</a>
                <a class="attendance-tab <?= $plan_tab === 'Yearly' ? 'active' : '' ?>" href="attendance.php?plan_tab=Yearly&amp;attendance_date=<?= h($selected_date) ?>">Yearly</a>
            </div>
        </div>

        <section class="summary-grid">
            <div class="summary-card"><div class="summary-top"><span>Total Check-ins</span><span class="summary-icon"><i class="fas fa-sign-in-alt"></i></span></div><strong class="summary-value"><?= number_format($total_checkins) ?></strong><span class="summary-caption">Attendance records for selected date</span></div>
            <?php if ($check_in_column !== "" && $check_out_column !== ""): ?>
                <div class="summary-card"><div class="summary-top"><span>Currently Inside</span><span class="summary-icon"><i class="fas fa-user-clock"></i></span></div><strong class="summary-value"><?= number_format($current_inside) ?></strong><span class="summary-caption">Members without a recorded check-out</span></div>
                <div class="summary-card"><div class="summary-top"><span>Checked Out</span><span class="summary-icon"><i class="fas fa-sign-out-alt"></i></span></div><strong class="summary-value"><?= number_format($checked_out) ?></strong><span class="summary-caption">Completed attendance visits</span></div>
            <?php endif; ?>
            <?php if ($late_arrivals !== null): ?>
                <div class="summary-card"><div class="summary-top"><span>Late Arrivals</span><span class="summary-icon"><i class="fas fa-clock"></i></span></div><strong class="summary-value"><?= number_format($late_arrivals) ?></strong><span class="summary-caption">Based on the attendance status field</span></div>
            <?php endif; ?>
        </section>

        <?php if ($msg !== ""): ?>

            <div class="msg <?= h($type) ?>">

                <?= h($msg) ?>

            </div>

        <?php endif; ?>

        <div class="filter-bar">
            <div class="filter-left">
                <div class="filter-title"><i class="fas fa-sliders-h"></i> Filter Attendance Records</div>
            </div>
            <form method="GET" class="filter-form" id="filter-form">
                <input type="hidden" name="plan_tab" value="<?= h($plan_tab) ?>">
                <label class="select-wrap">
                    <select name="membership_filter" id="membership_filter" aria-label="Filter by membership">
                        <?php foreach ($membership_options as $option): ?>
                            <option value="<?= h($option) ?>" <?= $membership_filter === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <input type="date" name="attendance_date" class="search-input" value="<?= h($selected_date) ?>" aria-label="Select attendance date">
                <input type="text" name="search" class="search-input" placeholder="Search member..." value="<?= h($search) ?>" id="search-input">
                <button type="submit"><i class="fas fa-search"></i> Apply Filters</button>
                <a class="reset-link" href="attendance.php?plan_tab=<?= urlencode($plan_tab) ?>">Reset</a>
            </form>
        </div>
        <div class="table-card">
            <div class="table-heading">
                <h2><i class="fas fa-clipboard-list"></i> Attendance Records</h2>
                <span><?= h(date('F j, Y', strtotime($selected_date))) ?> &nbsp;·&nbsp; <?= number_format($total_entries) ?> records</span>
            </div>
            <div class="table-wrap" style="max-height: 500px; overflow-y: auto;">

                <table>

                    <thead>

                        <tr>

                            <th>Member</th>

                            <th>Membership</th>

                            <th>Check-in Time</th>

                            <th>Check-out Time</th>

                            <th>Visit Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!empty($attendance_rows)): ?>

                            <?php foreach ($attendance_rows as $record): ?>

                                <tr>

                                    <td><div class="member-cell"><span class="member-avatar"><?= h(strtoupper(substr((string)($record['full_name'] ?? 'M'), 0, 1))) ?></span><span class="member-name"><?= h($record['full_name'] ?? 'Unknown Member') ?></span></div></td>

                                    <td><span class="membership-chip"><?= h($record['membership_name'] ?? 'Membership Unavailable') ?></span></td>

                                    <td><?= formatTimeValue($record['check_in_time'] ?? null) ?></td>

                                    <td><?= !empty($record['check_out_time']) ? h(formatTimeValue($record['check_out_time'])) : '<span class="attendance-status inside">Not checked out</span>' ?></td>

                                    <td>
                                        <?php
                                            $rowStatus = strtolower(trim((string)($record['attendance_status'] ?? '')));
                                            if (strpos($rowStatus, 'late') !== false) {
                                                echo '<span class="attendance-status late">Late Arrival</span>';
                                            } elseif (!empty($record['check_out_time'])) {
                                                echo '<span class="attendance-status completed">Checked Out</span>';
                                            } elseif (!empty($record['check_in_time'])) {
                                                echo '<span class="attendance-status inside">Currently Inside</span>';
                                            } else {
                                                echo '<span class="attendance-status unknown">Status Unavailable</span>';
                                            }
                                        ?>
                                    </td>

                                    <td><a href="members.php?edit=<?= (int)($record['user_id'] ?? 0) ?>" class="view-btn">View Member</a></td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6" class="empty-state">No attendance records found for this filter.</td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div class="table-footer">

                <div>

                    Showing <?= (int)$showing_start ?> to <?= (int)$showing_end ?> of <?= (int)$total_entries ?> entries

                </div>

                <div class="pagination">

                    <?php if ($page > 1): ?>

                        <a class="page-btn" href="<?= h(buildAttendanceUrl($page - 1, $plan_tab, $membership_filter, $search, $selected_date)) ?>">Previous</a>

                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                        <a class="page-number <?= $i === $page ? 'active' : '' ?>" href="<?= h(buildAttendanceUrl($i, $plan_tab, $membership_filter, $search, $selected_date)) ?>">

                            <?= $i ?>

                        </a>

                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>

                        <a class="page-btn" href="<?= h(buildAttendanceUrl($page + 1, $plan_tab, $membership_filter, $search, $selected_date)) ?>">Next</a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('filter-form');
    const membershipFilter = document.getElementById('membership_filter');
    const searchInput = document.getElementById('search-input');
    const dateInput = filterForm ? filterForm.querySelector('input[name="attendance_date"]') : null;

    if (!filterForm) return;
    if (membershipFilter) membershipFilter.addEventListener('change', function () { filterForm.submit(); });
    if (dateInput) dateInput.addEventListener('change', function () { filterForm.submit(); });

    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () { filterForm.submit(); }, 500);
        });
    }
});
</script>

</body>

</html>