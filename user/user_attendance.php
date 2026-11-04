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



function buildAttendanceUrl($page)

{

    return 'user_attendance.php?' . http_build_query(['p' => $page]);

}



mysqli_query($conn, "

    CREATE TABLE IF NOT EXISTS attendance (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        check_in_time DATETIME NOT NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4

");



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



$user_id = (int) $_SESSION['user_id'];



$userQuery = mysqli_query($conn, "

    SELECT full_name, username, status, membership_plan, member_since, is_active

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



$userName = $user['full_name'] ?? ($user['username'] ?? 'Member');

$userStatus = $user['status'] ?? 'Deactivated';

$isActive = (int) ($user['is_active'] ?? 0);



if ($isActive !== 1 || $userStatus !== 'Active') {

    session_unset();

    session_destroy();

    header("Location: index.php");

    exit();

}



$todayCheckInQuery = mysqli_query($conn, "

    SELECT check_in_time

    FROM attendance

    WHERE user_id = $user_id

      AND DATE(check_in_time) = CURDATE()

    ORDER BY check_in_time DESC

    LIMIT 1

");



$todayCheckInRow = $todayCheckInQuery ? mysqli_fetch_assoc($todayCheckInQuery) : null;

$hasCheckedInToday = !empty($todayCheckInRow);

$todayCheckInText = $hasCheckedInToday

    ? date('h:i A', strtotime($todayCheckInRow['check_in_time']))

    : 'No check-in yet today';



$perPage = 5;

$currentPage = max(1, (int) ($_GET['p'] ?? 1));



$countQuery = mysqli_query($conn, "

    SELECT COUNT(*) AS total

    FROM attendance

    WHERE user_id = $user_id

");



$countRow = $countQuery ? mysqli_fetch_assoc($countQuery) : ['total' => 0];

$totalRecords = (int) ($countRow['total'] ?? 0);

$totalPages = max(1, (int) ceil($totalRecords / $perPage));

$currentPage = min($currentPage, $totalPages);

$offset = ($currentPage - 1) * $perPage;



$attendanceQuery = mysqli_query($conn, "

    SELECT id, check_in_time

    FROM attendance

    WHERE user_id = $user_id

    ORDER BY check_in_time DESC

    LIMIT $offset, $perPage

");



$attendanceRows = [];

if ($attendanceQuery) {

    while ($row = mysqli_fetch_assoc($attendanceQuery)) {

        $attendanceRows[] = $row;

    }

}



$showingFrom = $totalRecords > 0 ? $offset + 1 : 0;

$showingTo = min($offset + $perPage, $totalRecords);



$navLinks = [

    'user_dashboard.php' => ['Dashboard Overview', false, 'fas fa-home'],

    'user_attendance.php' => ['Attendance', true, 'fas fa-calendar-check'],

    'user_membership.php' => ['Membership', false, 'fas fa-id-card'],

    'user_presets.php' => ['Workout Presets', false, 'fas fa-dumbbell'],

    '../logout.php?logout=true' => ['Logout', false, 'fas fa-sign-out-alt'],

];

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Attendance</title>



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

            font-size: 18px;

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



        .attendance-panel {

            background: rgba(61, 41, 100, 0.16);

            backdrop-filter: blur(12px);

            -webkit-backdrop-filter: blur(12px);

            border: 1px solid rgba(202, 123, 255, 0.22);

            padding: 20px;

            border-radius: 14px;

            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.22);

        }



        .checkin-area {

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            gap: 18px;

            padding: 18px 10px 24px;

            border-bottom: 1px solid rgba(255, 255, 255, 0.12);

            margin-bottom: 24px;

        }



        .checkin-sign {

            min-width: 360px;

            max-width: 100%;

            padding: 16px 30px;

            border: 1px solid rgba(202, 123, 255, 0.30);

            border-radius: 12px;

            background: linear-gradient(180deg, rgba(162, 69, 255, 0.95), rgba(124, 44, 244, 0.95));

            color: #fff;

            font-size: 22px;

            font-weight: 700;

            box-shadow: 0 8px 18px rgba(124, 44, 244, 0.35);

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 14px;

            font-family: inherit;

            cursor: default;

            user-select: none;

            pointer-events: none;

        }



        .checkin-sign i {

            font-size: 30px;

        }



        .checkin-sign.pending {

            opacity: 0.78;

        }



        .today-status {

            font-size: 16px;

            color: #e9d7ff;

            text-align: center;

        }



        .log-card {

            background: rgba(44, 27, 83, 0.14);

            backdrop-filter: blur(10px);

            -webkit-backdrop-filter: blur(10px);

            border: 1px solid rgba(202, 123, 255, 0.18);

            border-radius: 14px;

            overflow: hidden;

        }



        .log-card-header {

            padding: 20px 24px 12px;

            font-size: 20px;

            font-weight: 700;

            color: #f5eaff;

        }



        .table-container {

            width: 100%;

            overflow-x: auto;

        }



        table {

            width: 100%;

            border-collapse: collapse;

            background: transparent;

        }



        table th,

        table td {

            padding: 16px 24px;

            text-align: left;

            border-bottom: 1px solid rgba(255, 255, 255, 0.14);

            font-size: 15px;

        }



        table th {

            background: rgba(124, 77, 255, 0.20);

            color: #f5eaff;

            font-weight: 700;

            letter-spacing: 0.4px;

        }



        table td {

            background: rgba(255, 255, 255, 0.04);

            color: #ffffff;

            font-weight: 500;

            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);

        }



        table tbody tr:hover td {

            background: rgba(202, 123, 255, 0.10);

            transition: 0.25s ease;

        }



        .empty-row {

            text-align: center;

            padding: 28px 16px !important;

        }



        .log-footer {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 12px;

            padding: 16px 20px;

            flex-wrap: wrap;

            background: rgba(30, 14, 59, 0.34);

        }



        .entries-text {

            color: #f3e7ff;

            font-size: 15px;

            font-weight: 500;

        }



        .pagination {

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;

        }



        .page-link,

        .page-number {

            color: white;

            font-weight: 600;

            text-decoration: none;

            padding: 10px 16px;

            background: rgba(124, 77, 255, 0.25);

            border: 1px solid rgba(202, 123, 255, 0.25);

            border-radius: 8px;

            min-width: 52px;

            text-align: center;

        }



        .page-link.disabled {

            opacity: 0.45;

            pointer-events: none;

        }



        .page-number.active {

            background: linear-gradient(180deg, #a245ff, #7c2cf4);

            box-shadow: 0 4px 10px rgba(124, 44, 244, 0.35);

        }



        @media (max-width: 900px) {

            .checkin-sign {

                min-width: 100%;

                font-size: 20px;

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



            .log-footer {

                flex-direction: column;

                align-items: stretch;

            }



            .pagination {

                justify-content: center;

            }



            table th,

            table td {

                padding: 14px 16px;

            }

        }





        /* VIGOR GYM USER INTERFACE REFINEMENTS */

        .sidebar-title-container {

            padding: 20px 12px 18px;

            margin-bottom: 18px;

            background: linear-gradient(135deg, rgba(85, 35, 125, 0.78), rgba(35, 10, 55, 0.96));

            text-align: center;

            border: 1px solid rgba(202, 123, 255, 0.25);

            border-radius: 12px;

        }



        .sidebar-brand-name {

            color: #ffffff;

            font-size: 23px;

            font-weight: 800;

            letter-spacing: 2px;

            line-height: 1.2;

        }



        .sidebar-brand-name span {

            color: #d9a5ff;

        }



        .sidebar-brand-subtitle {

            margin-top: 6px;

            color: #eadcff;

            font-size: 10px;

            font-weight: 700;

            letter-spacing: 3px;

        }



        .sidebar-profile {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 12px;

            margin-bottom: 20px;

            background: rgba(171, 82, 242, 0.13);

            border: 1px solid rgba(202, 123, 255, 0.25);

            border-radius: 12px;

        }



        .profile-icon {

            width: 42px;

            height: 42px;

            min-width: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 50%;

            background: linear-gradient(135deg, #d18aff, #8734c5);

            color: #ffffff;

            font-size: 18px;

        }



        .profile-info {

            display: flex;

            flex-direction: column;min-width: 0;

        }



        .profile-info strong {

            color: #ffffff;

            font-size: 13px;

            font-weight: 700;

            overflow-wrap: anywhere;

        }



        .profile-info span {

            color: #d9baff;

            font-size: 11px;

            margin-top: 3px;

        }



        nav.sidebar ul li {

            margin: 10px 0;

        }



        nav.sidebar ul li a {

            display: flex;

            align-items: center;

            gap: 13px;

            min-height: 48px;

            padding: 12px 13px;

            border-radius: 9px;

            border-left: 5px solid transparent;

            color: #ffffff;

            font-size: 20px;

            font-weight: 600;

            text-decoration: none;

            transition: background 0.25s ease, color 0.25s ease, padding 0.25s ease, box-shadow 0.25s ease;

        }



        nav.sidebar ul li a i {

            width: 22px;

            min-width: 22px;

            text-align: center;

            color: #d9a5ff;

            font-size: 16px;

        }



        nav.sidebar ul li a span {

            line-height: 1.4;

        }



        nav.sidebar ul li a:hover {

            background: rgba(171, 82, 242, 0.22);

            border-left-color: #ca7bff;

            padding-left: 18px;

            color: #f0caff;

            box-shadow: 0 3px 12px rgba(171, 82, 242, 0.12);

        }



        nav.sidebar ul li a.active {

            background: linear-gradient(90deg, rgba(171, 82, 242, 0.45), rgba(115, 45, 160, 0.20));

            border-left-color: #e2a5ff;

            color: #ffffff;

            font-weight: 700;

            box-shadow: 0 0 12px rgba(190, 100, 255, 0.18);

        }



        .page-heading {

            position: relative;

            padding: 4px 0 16px;

            margin-bottom: 2px;

        }



        .page-title {

            position: relative;

            display: inline-block;

            margin: 0 0 8px;

            color: #ffffff;

            font-size: 36px;

            font-weight: 800;

            line-height: 1.2;

            letter-spacing: -0.5px;

            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);

        }



        .page-title::after {

            content: "";

            display: block;

            width: 76px;

            height: 4px;

            margin-top: 10px;

            border-radius: 99px;

            background: linear-gradient(90deg, #e2a5ff, #8734c5);

        }



        .welcome-line {

            color: #eadcff;

            font-size: 14px;

            margin-top: 2px;

        }



        @media (max-width: 768px) {

            nav.sidebar {

                width: 100%;

                height: auto;

                position: relative;

                top: 0;

            }



            .page-title {

                font-size: 29px;

            }

        }



        /* Attendance table refinements */

        .attendance-panel,

        .log-card {

            border: 1px solid rgba(202, 123, 255, 0.25);

            border-radius: 16px;

            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.24);

        }



        .log-card-header {

            padding: 20px 22px 15px;

            color: #f3d8ff;

            font-size: 18px;

            font-weight: 700;

            border-bottom: 1px solid rgba(255, 255, 255, 0.09);

        }



        .table-container {

            padding: 10px 14px 14px;

        }



        table {

            border-collapse: separate;

            border-spacing: 0 7px;

        }



        table th {

            padding: 15px 20px;

            background: rgba(124, 77, 255, 0.30);

            color: #f8edff;

            font-size: 13px;

            font-weight: 700;

            letter-spacing: 0.6px;

            text-transform: uppercase;

            border: 0;

        }



        table th:first-child { border-radius: 9px 0 0 9px; }

        table th:last-child { border-radius: 0 9px 9px 0; }



        table td {

            padding: 15px 20px;

            background: rgba(255, 255, 255, 0.055);

            color: #ffffff;

            font-size: 14px;

            border-top: 1px solid rgba(255, 255, 255, 0.055);

            border-bottom: 1px solid rgba(255, 255, 255, 0.055);

        }



        table td:first-child {

            border-left: 1px solid rgba(255, 255, 255, 0.055);

            border-radius: 9px 0 0 9px;

        }



        table td:last-child {

            border-right: 1px solid rgba(255, 255, 255, 0.055);

            border-radius: 0 9px 9px 0;

        }



        table tbody tr:hover td {

            background: rgba(171, 82, 242, 0.16);

        }



        .empty-row {

            border-radius: 9px !important;

        }

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

                <strong><?= h($userName) ?></strong>

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

            <h1 class="page-title">Daily Attendance Log</h1>

            <div class="welcome-line">Welcome back, <?= h($userName) ?>!</div>

        </div>



        <div class="attendance-panel">

            <div class="checkin-area">

                <div class="checkin-sign <?= $hasCheckedInToday ? 'checked' : 'pending' ?>">

                    <i class="fas fa-check-circle"></i>

                    <span><?= $hasCheckedInToday ? 'Checked-in' : 'Not checked in yet' ?></span>

                </div>



                <div class="today-status">

                    <?= $hasCheckedInToday

                        ? 'You have already checked in at ' . h($todayCheckInText) . ' today.'

                        : 'Please use the check-in button on your dashboard overview.' ?>

                </div>

            </div>



            <div class="log-card">

                <div class="log-card-header">Daily Attendance Log</div>



                <div class="table-container">

                    <table>

                        <thead>

                            <tr>

                                <th>Date</th>

                                <th>Check-in Time</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php if (empty($attendanceRows)): ?>

                                <tr>

                                    <td colspan="2" class="empty-row">No attendance records found.</td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($attendanceRows as $row): ?>

                                    <tr>

                                        <td><?= h(date('m/d/Y', strtotime($row['check_in_time']))) ?></td>

                                        <td><?= h(date('h:i A', strtotime($row['check_in_time']))) ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>



                <div class="log-footer">

                    <div class="entries-text">

                        Showing <?= $showingFrom ?> to <?= $showingTo ?> entries

                    </div>



                    <div class="pagination">

                        <a class="page-link <?= $currentPage <= 1 ? 'disabled' : '' ?>"

                           href="<?= h(buildAttendanceUrl(max(1, $currentPage - 1))) ?>">

                            Previous

                        </a>



                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                            <a class="page-number <?= $i === $currentPage ? 'active' : '' ?>"

                               href="<?= h(buildAttendanceUrl($i)) ?>">

                                <?= $i ?>

                            </a>

                        <?php endfor; ?>



                        <a class="page-link <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"

                           href="<?= h(buildAttendanceUrl(min($totalPages, $currentPage + 1))) ?>">

                            Next

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>



</body>

</html>