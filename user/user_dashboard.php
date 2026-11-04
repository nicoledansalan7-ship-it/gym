<?php

session_start();

date_default_timezone_set('Asia/Manila');

mysqli_report(MYSQLI_REPORT_OFF);



$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");

if (!$conn) die("Database connection failed.");

mysqli_set_charset($conn, "utf8mb4");



function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function queryValue($conn, $sql, $key, $default = 0) {

    $result = mysqli_query($conn, $sql);

    if (!$result) return $default;

    $row = mysqli_fetch_assoc($result);

    return $row[$key] ?? $default;

}

function columnExists($conn, $table, $column) {

    $table = mysqli_real_escape_string($conn, $table);

    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM \`$table\` LIKE '$column'");

    return ($check && mysqli_num_rows($check) > 0);

}

function calculateExpirationDate($memberSince, $membershipPlan) {

    if (empty($memberSince)) return '';

    $membershipPlan = strtolower(trim((string)$membershipPlan));

    $baseDate = strtotime($memberSince);

    if (!$baseDate) return '';

    if (strpos($membershipPlan, 'monthly') !== false) return date('Y-m-d', strtotime('+1 month', $baseDate));

    if (strpos($membershipPlan, 'quarter') !== false) return date('Y-m-d', strtotime('+3 months', $baseDate));

    if (strpos($membershipPlan, 'annual') !== false || strpos($membershipPlan, 'year') !== false) return date('Y-m-d', strtotime('+1 year', $baseDate));

    return '';

}



// Attendance table creation

mysqli_query($conn, "

    CREATE TABLE IF NOT EXISTS attendance (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        check_in_time DATETIME NOT NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4

");



// Session and role check

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'member') {

    header("Location: index.php"); exit();

}



$user_id = (int)$_SESSION['user_id'];



// Fetch user info

$hasExpirationDateColumn = columnExists($conn, 'users', 'expiration_date');

$userFields = "full_name, username, status, membership_plan, member_since, is_active";

if ($hasExpirationDateColumn) $userFields .= ", expiration_date";



$userQuery = mysqli_query($conn, "SELECT $userFields FROM users WHERE id = $user_id LIMIT 1");

if (!$userQuery || mysqli_num_rows($userQuery) === 0) {

    session_unset(); session_destroy();

    header("Location: index.php"); exit();

}

$user = mysqli_fetch_assoc($userQuery);

$user_name = $user['full_name'] ?? ($user['username'] ?? 'Member');

$membership_status = $user['status'] ?? 'Deactivated';

$membership_plan = $user['membership_plan'] ?? 'Standard';

$member_since = $user['member_since'] ?? '';

$is_active = (int)($user['is_active'] ?? 0);

$expiration_date = $hasExpirationDateColumn ? ($user['expiration_date'] ?? '') : '';



if ($is_active !== 1 || $membership_status !== 'Active') {

    session_unset(); session_destroy();

    header("Location: index.php"); exit();

}



// Compute expiration date

if (empty($expiration_date) && !empty($member_since) && !empty($membership_plan)) {

    $computedExpirationDate = calculateExpirationDate($member_since, $membership_plan);

    if ($computedExpirationDate !== '') {

        $expiration_date = $computedExpirationDate;

        if ($hasExpirationDateColumn) {

            $safeExpirationDate = mysqli_real_escape_string($conn, $computedExpirationDate);

            mysqli_query($conn, "UPDATE users SET expiration_date = '$safeExpirationDate' WHERE id = $user_id");

        }

    }

}



// Flash message

$msg = $type = '';

if (isset($_SESSION['dashboard_flash'])) {

    $msg = $_SESSION['dashboard_flash']['message'] ?? '';

    $type = $_SESSION['dashboard_flash']['type'] ?? 'success';

    unset($_SESSION['dashboard_flash']);

}



// Today's check-in

$todayCheckInQuery = mysqli_query($conn, "

    SELECT check_in_time

    FROM attendance

    WHERE user_id = $user_id AND DATE(check_in_time) = CURDATE()

    ORDER BY check_in_time DESC

    LIMIT 1

");

$todayCheckInRow = $todayCheckInQuery ? mysqli_fetch_assoc($todayCheckInQuery) : null;

$hasCheckedInToday = !empty($todayCheckInRow);

$todayCheckInText = '';

if ($hasCheckedInToday && !empty($todayCheckInRow['check_in_time'])) {

    $todayCheckInText = date('h:i A', strtotime($todayCheckInRow['check_in_time']));

}

$_SESSION['hasCheckedInToday'] = $hasCheckedInToday;



// Handle POST check-in

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in']) && !$hasCheckedInToday) {

    $checkInTime = date("Y-m-d H:i:s");

    $insertCheckIn = mysqli_query($conn, "INSERT INTO attendance (user_id, check_in_time) VALUES ($user_id, '$checkInTime')");

    if ($insertCheckIn) {

        $_SESSION['hasCheckedInToday'] = true;

        $_SESSION['dashboard_flash'] = [

            'message' => 'You have successfully checked in at ' . date('h:i A', strtotime($checkInTime)) . ' today.',

            'type' => 'success'

        ];

    } else {

        $_SESSION['dashboard_flash'] = [

            'message' => 'Unable to record your check-in right now.',

            'type' => 'error'

        ];

    }

    header("Location: user_dashboard.php"); exit();

}



// Stats

$total_checkins = (int) queryValue($conn, "SELECT COUNT(*) AS total FROM attendance WHERE user_id = $user_id", 'total', 0);

$workout_presets = (int) queryValue($conn, "SELECT COUNT(*) AS total FROM workout_presets WHERE user_id = $user_id", 'total', 0);

$last_check_in_raw = queryValue($conn, "SELECT check_in_time AS last_time FROM attendance WHERE user_id = $user_id ORDER BY check_in_time DESC LIMIT 1", 'last_time', '');

$last_check_in_time = $last_check_in_raw ? date("F j, Y, g:i A", strtotime($last_check_in_raw)) : "No check-in yet.";



// Workout history

$workout_history = [];

$workouts = mysqli_query($conn, "SELECT workout_name, workout_date FROM workouts WHERE user_id = $user_id ORDER BY workout_date DESC LIMIT 3");

if ($workouts) while ($w = mysqli_fetch_assoc($workouts)) $workout_history[] = trim($w['workout_name'] . (!empty($w['workout_date']) ? ' - ' . date('M j, Y', strtotime($w['workout_date'])) : ''));

if (empty($workout_history)) $workout_history[] = "No workout history available.";



$navLinks = [

    'user_dashboard.php' => ['Dashboard Overview', true, 'fas fa-home'],

    'user_attendance.php' => ['Attendance', false, 'fas fa-calendar-check'],

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

<title>User Dashboard</title>

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



        .members-list {

            background: rgba(61, 41, 100, 0.16);

            backdrop-filter: blur(12px);

            -webkit-backdrop-filter: blur(12px);

            border: 1px solid rgba(202, 123, 255, 0.22);

            padding: 20px;

            border-radius: 14px;

            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.22);

        }



        .msg {

            padding: 14px 18px;

            font-size: 15px;

            border-radius: 10px;

            border: 1px solid rgba(255, 255, 255, 0.05);

            background: rgba(255, 255, 255, 0.03);

            margin-top: 16px;

            text-align: center;

        }



        .msg.success {

            color: #d8ffe5;

            box-shadow: 0 0 10px rgba(80, 220, 140, 0.08);

        }



        .msg.error {

            color: #ffb0d2;

            box-shadow: 0 0 10px rgba(255, 120, 180, 0.06);

        }



        .checkin-panel {

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            gap: 16px;

            padding: 8px 10px 0;

        }



        .dashboard-checkin-btn,

        .dashboard-checkin-sign {

            min-width: 360px;

            max-width: 100%;

            padding: 16px 30px;

            border: 1px solid rgba(202, 123, 255, 0.30);

            border-radius: 12px;

            background: linear-gradient(180deg, rgba(162, 69, 255, 0.95), rgba(124, 44, 244, 0.95));

            color: #fff;

            font-size: 22px;

            font-weight: 700;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 14px;

            font-family: inherit;

            box-shadow: 0 8px 18px rgba(124, 44, 244, 0.35);

        }



        .dashboard-checkin-btn {

            cursor: pointer;

            transition: all 0.25s ease;

        }



        .dashboard-checkin-btn:hover {

            transform: translateY(-1px);

            box-shadow: 0 10px 22px rgba(124, 44, 244, 0.42);

        }



        .dashboard-checkin-sign {

            cursor: default;

            pointer-events: none;

            user-select: none;

        }



        .dashboard-checkin-btn i,

        .dashboard-checkin-sign i {

            font-size: 30px;

        }



        .checkin-note {

            font-size: 16px;

            color: #eadcff;

            text-align: center;

        }



        .overview {

            display: flex;

            justify-content: space-between;

            gap: 15px;

            flex-wrap: wrap;

        }



        .overview-item {

            background: rgba(92, 61, 176, 0.18);

            backdrop-filter: blur(10px);

            -webkit-backdrop-filter: blur(10px);

            border: 1px solid rgba(202, 123, 255, 0.25);

            padding: 20px;

            border-radius: 14px;

            width: calc(33.333% - 10px);

            text-align: center;

            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);

        }



        .overview-item h3 {

            color: #e9c8ff;

            margin-bottom: 10px;

            font-size: 16px;

            font-weight: 600;

        }



        .overview-item p,

        .overview-item li {

            font-size: 18px;

            font-weight: 500;

            color: #fff;

            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);

        }



        .big-text {

            font-size: 26px !important;

            font-weight: 700 !important;

        }



        .sub-info {

            margin-top: 8px;

            font-size: 14px !important;

            font-weight: 500 !important;

            color: #eadcff !important;

            line-height: 1.5;

        }



        .workout-history {

            list-style: none;

            padding: 0;

        }



        .workout-history li {

            margin-bottom: 8px;

        }



        @media (max-width: 900px) {

            .overview-item {

                width: calc(50% - 10px);

            }



            .dashboard-checkin-btn,

            .dashboard-checkin-sign {

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



            .overview-item {

                width: 100%;

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

            flex-direction: column;

            min-width: 0;

        }



        .profile-info strong {

            color: #ffffff;

            font-size: 13px;

            font-weight: 700;overflow-wrap: anywhere;

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



        /* Dashboard overview card refinements */

        .members-list {

            border: 1px solid rgba(202, 123, 255, 0.25);

            border-radius: 16px;

            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.24);

        }



        .overview {

            display: grid;

            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));

            gap: 18px;

            align-items: stretch;

        }



        .overview-item {

            width: auto;

            min-height: 135px;

            display: flex;

            flex-direction: column;

            justify-content: center;

            padding: 22px;

            text-align: left;

            background: linear-gradient(145deg, rgba(105, 65, 170, 0.30), rgba(44, 19, 79, 0.42));

            border: 1px solid rgba(202, 123, 255, 0.28);

            border-radius: 16px;

            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.22);

            transition: transform 0.22s ease, border-color 0.22s ease, background 0.22s ease;

        }



        .overview-item:hover {

            transform: translateY(-3px);

            border-color: rgba(220, 166, 255, 0.55);

            background: linear-gradient(145deg, rgba(125, 75, 195, 0.34), rgba(54, 23, 94, 0.48));

        }



        .overview-item h3 {

            margin-bottom: 12px;

            color: #e9c8ff;

            font-size: 13px;

            font-weight: 700;

            letter-spacing: 0.6px;

            text-transform: uppercase;

        }



        .overview-item p,

        .overview-item li {

            font-size: 15px;

            line-height: 1.6;

        }



        .overview-item .big-text {

            font-size: 24px !important;

            overflow-wrap: anywhere;

        }



        .workout-history li {

            padding: 7px 0;

            border-bottom: 1px solid rgba(255, 255, 255, 0.08);

        }



        .workout-history li:last-child {

            border-bottom: 0;

        }



        @media (max-width: 768px) {

            .overview {

                grid-template-columns: 1fr;

            }

            .overview-item {

                min-height: 110px;

            }

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

            <h1 class="page-title">Dashboard Overview</h1>

            <div class="welcome-line">Welcome back, <?= h($user_name) ?>!</div>

        </div>



        <?php if (!$_SESSION['hasCheckedInToday']): ?>

            <!-- Step 1: Only Check-in button -->

            <div class="members-list">

                <div class="checkin-panel">

                    <form method="POST" action="">

                        <button type="submit" name="check_in" class="dashboard-checkin-btn">

                            <i class="fas fa-check-circle"></i><span>Check-in</span>

                        </button>

                    </form>

                    <div class="checkin-note">Click here to record your attendance.</div>

                </div>

            </div>

        <?php else: ?>

            <!-- Step 2: Full dashboard -->

            <div class="members-list">

                <div class="checkin-panel">

                    <div class="dashboard-checkin-sign">

                        <i class="fas fa-check-circle"></i><span>Checked-in</span>

                    </div>

                    <?php if ($msg !== ''): ?>

                        <div class="msg <?= h($type) ?>"><?= h($msg) ?></div>

                    <?php endif; ?>

                </div>

            </div>



            <div class="overview">

                <!-- Dashboard overview panels -->

                <div class="overview-item"><h3>Membership Status</h3><p class="big-text"><?= h($membership_status) ?></p></div>

                <div class="overview-item"><h3>Membership Plan</h3><p class="big-text"><?= h($membership_plan) ?></p></div>

                <div class="overview-item"><h3>Member Since</h3><p><?= $member_since ? h(date('F j, Y', strtotime($member_since))) : 'Not set' ?></p>

                    <p class="sub-info">Expiration Date: <?= $expiration_date ? h(date('F j, Y', strtotime($expiration_date))) : 'Not set' ?></p>

                </div>

                <div class="overview-item"><h3>Total Check-ins</h3><p class="big-text"><?= (int) $total_checkins ?></p></div>

                <div class="overview-item"><h3>Workout Presets</h3><p class="big-text"><?= (int) $workout_presets ?></p></div>

                <div class="overview-item"><h3>Last Check-in</h3><p><?= h($last_check_in_time) ?></p></div>

                <div class="overview-item"><h3>Workout History</h3><ul class="workout-history"><?php foreach ($workout_history as $workout): ?><li><?= h($workout) ?></li><?php endforeach; ?></ul></div>

            </div>

        <?php endif; ?>

    </div>

</div>



<!-- Auto-hide flash message -->

<script>

document.addEventListener("DOMContentLoaded", function() {

    const msg = document.querySelector('.msg');

    if (msg) {

        setTimeout(() => {

            msg.style.transition = "opacity 0.5s ease";

            msg.style.opacity = "0";

            setTimeout(() => msg.remove(), 500);

        }, 4000);

    }

});

</script>

</body>

</html>