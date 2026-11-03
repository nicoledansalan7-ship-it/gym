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
    $today = date('Y-m-d');
    $where_parts[] = "DATE(attendance.`$check_in_column`) = '$today'";
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

function buildAttendanceUrl($pageNumber, $plan_tab, $membership_filter, $search)
{
    $params = [
        'page' => $pageNumber,
        'plan_tab' => $plan_tab
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

        nav.sidebar ul li a:hover,
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

        table tbody tr:nth-child(even) td {
            background: rgba(255, 255, 255, 0.05);
        }

        table tbody tr:hover td {
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
                    <a href="<?= h($href) ?>" class="<?= $isActive ?>">
                        <i class="<?= h($icon) ?>"></i> <?= h($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="main-content">
        <h1 class="page-title">Attendance</h1>

        <div class="top-bar">
            <div class="attendance-tabs">
                <a class="attendance-tab <?= $plan_tab === 'Monthly' ? 'active' : '' ?>" href="attendance.php?plan_tab=Monthly">Monthly</a>
                <a class="attendance-tab <?= $plan_tab === 'Quarterly' ? 'active' : '' ?>" href="attendance.php?plan_tab=Quarterly">Quarterly</a>
                <a class="attendance-tab <?= $plan_tab === 'Yearly' ? 'active' : '' ?>" href="attendance.php?plan_tab=Yearly">Yearly</a>
            </div>
        </div>

        <?php if ($msg !== ""): ?>
            <div class="msg <?= h($type) ?>">
                <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <div class="filter-bar">
            <div class="filter-left">
                <div class="filter-title">Daily Check-in Logs</div>
            </div>

            <form method="GET" class="filter-form" id="filter-form">
                <input type="hidden" name="plan_tab" value="<?= h($plan_tab) ?>">

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search member..."
                    value="<?= h($search) ?>"
                    id="search-input"
                >
            </form>
        </div>

        <div class="table-card">
            <div class="table-wrap" style="max-height: 500px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Membership</th>
                            <th>Check-in Time</th>
                            <th>Check-out Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($attendance_rows)): ?>
                            <?php foreach ($attendance_rows as $record): ?>
                                <tr>
                                    <td><?= h($record['full_name'] ?? 'Unknown Member') ?></td>
                                    <td><?= h($record['membership_name'] ?? 'Membership Unavailable') ?></td>
                                    <td><?= formatTimeValue($record['check_in_time'] ?? null) ?></td>
                                    <td>
                                        <?php
                                        if (!empty($record['check_out_time'])) {
                                            echo h(formatTimeValue($record['check_out_time']));
                                        } elseif (!empty($record['check_in_time'])) {
                                            echo "Active";
                                        } else {
                                            echo "--:--";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="members.php?edit=<?= (int)($record['user_id'] ?? 0) ?>" class="view-btn">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">No attendance records found for this filter.</td>
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
                        <a class="page-btn" href="<?= h(buildAttendanceUrl($page - 1, $plan_tab, $membership_filter, $search)) ?>">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a class="page-number <?= $i === $page ? 'active' : '' ?>" href="<?= h(buildAttendanceUrl($i, $plan_tab, $membership_filter, $search)) ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a class="page-btn" href="<?= h(buildAttendanceUrl($page + 1, $plan_tab, $membership_filter, $search)) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const filterForm = document.getElementById('filter-form');
    const membershipFilter = document.getElementById('membership_filter');
    const searchInput = document.getElementById('search-input');

    membershipFilter.addEventListener('change', function () {
        filterForm.submit();
    });

    let searchTimeout;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
            filterForm.submit();
        }, 400);
    });
</script>

</body>
</html>