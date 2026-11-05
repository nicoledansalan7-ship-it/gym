<?php
session_start();

$sessionRole = strtolower(trim($_SESSION['role'] ?? ''));

if (!isset($_SESSION['user_id']) || $sessionRole !== 'admin') {
    header("Location: index.php?page=login");
    exit();
}

require_once dirname(__DIR__) . '/db.php';
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
        echo "<tr><td colspan='" . count($columns) . "' class='empty-cell'>Query failed.</td></tr>";
        return;
    }

    if (mysqli_num_rows($result) === 0) {
        echo "<tr><td colspan='" . count($columns) . "' class='empty-cell'>No records found.</td></tr>";
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

/* Existing dashboard statistics */
$activeSubscriptions = (int) fetchValue(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE status = 'Active'
       AND is_active = 1
       AND role != 'admin'",
    'total'
);

$stats = [
    ['Total Members', fetchValue($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'member'", 'total'), 'fas fa-users'],
    ['Active Subscriptions', $activeSubscriptions, 'fas fa-id-card'],
    ['Daily Check-ins', fetchValue($conn, "SELECT COUNT(*) AS checkins FROM attendance WHERE DATE(check_in_time) = CURDATE()", 'checkins'), 'fas fa-calendar-check'],
    ['Income Today', '₱' . number_format((float) fetchValue($conn, "SELECT COALESCE(SUM(amount), 0) AS income FROM payments WHERE DATE(payment_date) = CURDATE()", 'income'), 2), 'fas fa-wallet'],
];

/* Existing recent activity queries */
$recentSections = [
    [
        'title' => 'Last 5 Check-ins',
        'subtitle' => 'Latest member attendance records',
        'icon' => 'fas fa-calendar-check',
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
        'subtitle' => 'Latest recorded transactions',
        'icon' => 'fas fa-credit-card',
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
        'subtitle' => 'Recently registered members',
        'icon' => 'fas fa-user-plus',
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

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
            background: url('bg.png') center / cover no-repeat fixed;
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
        }

        /* Existing admin sidebar design retained */
        nav.sidebar, .sidebar {
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
        .sidebar-brand-name span { color: #d7a2ff; }

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

        nav.sidebar ul, .sidebar ul { list-style: none; padding: 0; margin: 0; }
        nav.sidebar ul li, .sidebar ul li { margin: 10px 0; }

        nav.sidebar ul li a, .sidebar ul li a {
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

        nav.sidebar ul li a i, .sidebar ul li a i {
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
        .sidebar ul li a.active i { color: #f1d7ff; }

        /* Dashboard page structure */
        .dashboard-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .page-heading {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 2px 2px 4px;
        }

        h1 {
            color: #fff;
            font-size: 36px;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: 0.2px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        }

        .page-subtitle {
            color: rgba(245, 229, 255, 0.82);
            font-size: 13px;
            font-weight: 500;
        }

        .dashboard-section {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .section-heading h2 {
            color: #fff;
            font-size: 19px;
            font-weight: 700;
            line-height: 1.3;
        }

        .section-heading p {
            margin-top: 3px;
            color: rgba(232, 200, 255, 0.82);
            font-size: 11px;
            font-weight: 500;
        }

        .section-indicator {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #e8c8ff;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .section-indicator i { color: #ca7bff; }

        /* Four equal-sized summary cards */
        .overview {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            width: 100%;
        }

        .overview-item {
            position: relative;
            min-width: 0;
            min-height: 142px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
            padding: 19px 20px;
            overflow: hidden;
            border: 1px solid var(--vg-glass-border);
            border-radius: 13px;
            background: linear-gradient(145deg, rgba(74, 39, 112, 0.58), rgba(44, 27, 83, 0.35));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
            transition: transform 0.25s ease, border-color 0.25s ease, background 0.25s ease;
        }

        .overview-item::after {
            content: "";
            position: absolute;
            width: 105px;
            height: 105px;
            right: -35px;
            bottom: -48px;
            border-radius: 50%;
            background: rgba(202, 123, 255, 0.10);
            pointer-events: none;
        }

        .overview-item:hover {
            transform: translateY(-3px);
            border-color: rgba(202, 123, 255, 0.48);
            background: linear-gradient(145deg, rgba(91, 45, 137, 0.65), rgba(48, 26, 86, 0.45));
        }

        .overview-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .overview-item h3 {
            color: #e8c8ff;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
        }

        .overview-icon {
            width: 38px;
            height: 38px;
            min-width: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(221, 176, 255, 0.28);
            border-radius: 10px;
            color: #f0d8ff;
            background: rgba(171, 82, 242, 0.22);
            font-size: 15px;
        }

        .overview-item p {
            color: #fff;
            font-size: clamp(21px, 2vw, 27px);
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.30);
        }

        /* Three evenly aligned recent activity cards */
        .recent-activities {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            align-items: stretch;
            gap: 16px;
            width: 100%;
        }

        .activity-card {
            min-width: 0;
            min-height: 255px;
            display: flex;
            flex-direction: column;
            padding: 18px;
            border: 1px solid var(--vg-glass-border);
            border-radius: 13px;
            background: rgba(44, 27, 83, 0.34);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
        }

        .activity-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            margin-bottom: 14px;
        }

        .activity-icon {
            width: 34px;
            height: 34px;
            min-width: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            color: #efd9ff;
            background: rgba(171, 82, 242, 0.22);
            border: 1px solid rgba(202, 123, 255, 0.22);
            font-size: 13px;
        }

        .activity-title-wrap { min-width: 0; }

        .recent-activities h4 {
            color: #f3e7ff;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.35;
        }

        .activity-subtitle {
            margin-top: 2px;
            color: rgba(232, 200, 255, 0.72);
            font-size: 10px;
            font-weight: 500;
        }

        .table-wrap {
            width: 100%;
            flex: 1;
            overflow-x: auto;
            border: 1px solid rgba(202, 123, 255, 0.12);
            border-radius: 9px;
        }

        table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.035);
        }

        table th, table td {
            padding: 11px 12px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.10);
            font-size: 11px;
            line-height: 1.5;
        }

        table th {
            background: rgba(124, 77, 255, 0.25);
            color: #f5eaff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.35px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        table td {
            background: rgba(255, 255, 255, 0.025);
            color: #fff;
            font-weight: 500;
        }

        table tbody tr:last-child td { border-bottom: none; }
        table tbody tr:hover td { background: rgba(202, 123, 255, 0.11); transition: background 0.2s ease; }
        .empty-cell { padding: 22px 12px; color: rgba(245, 229, 255, 0.72); text-align: center; }
        table td a { color: #f3b8ff; text-decoration: none; }

        @media (max-width: 1200px) {
            .overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .recent-activities { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .activity-card:last-child { grid-column: 1 / -1; }
        }

        @media (max-width: 900px) {
            .admin-dashboard { gap: 14px; padding: 12px; }
            nav.sidebar, .sidebar {
                width: 220px;
                min-width: 220px;
                flex-basis: 220px;
                padding: 15px;
            }
            .recent-activities { grid-template-columns: 1fr; }
            .activity-card:last-child { grid-column: auto; }
        }

        @media (max-width: 768px) {
            .admin-dashboard { flex-direction: column; }
            nav.sidebar, .sidebar {
                width: 100%;
                min-width: 0;
                flex: 0 0 auto;
                height: auto;
                min-height: 0;
                position: relative;
                top: 0;
            }
            nav.sidebar ul, .sidebar ul {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 4px 7px;
            }
            nav.sidebar ul li, .sidebar ul li { margin: 2px 0; }
            nav.sidebar ul li a, .sidebar ul li a {
                min-height: 46px;
                padding: 10px;
                font-size: 12px;
            }
            nav.sidebar ul li a i, .sidebar ul li a i {
                width: 18px;
                min-width: 18px;
                font-size: 14px;
            }
            h1 { font-size: 30px; }
        }

        @media (max-width: 480px) {
            .overview { grid-template-columns: 1fr; }
            .overview-item { min-height: 120px; }
            .section-heading { align-items: flex-start; }
            .section-indicator { display: none; }
            .activity-card { padding: 14px; }
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

    <main class="dashboard-content">
        <header class="page-heading">
            <h1>Admin Dashboard</h1>
            <p class="page-subtitle">A quick overview of your gym's current activity and performance.</p>
        </header>

        <section class="dashboard-section" aria-labelledby="overview-heading">
            <div class="section-heading">
                <div>
                    <h2 id="overview-heading">Overview</h2>
                    <p>Key membership and daily operation summaries</p>
                </div>
                <span class="section-indicator"><i class="fas fa-chart-pie"></i> Dashboard Summary</span>
            </div>

            <div class="overview">
                <?php foreach ($stats as [$label, $value, $icon]): ?>
                    <article class="overview-item">
                        <div class="overview-top">
                            <h3><?= safe($label) ?></h3>
                            <span class="overview-icon"><i class="<?= safe($icon) ?>"></i></span>
                        </div>
                        <p><?= safe($value) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="dashboard-section" aria-labelledby="activity-heading">
            <div class="section-heading">
                <div>
                    <h2 id="activity-heading">Recent Activities</h2>
                    <p>Latest attendance, payment, and registration records</p>
                </div>
                <span class="section-indicator"><i class="fas fa-history"></i> Latest Records</span>
            </div>

            <div class="recent-activities">
                <?php foreach ($recentSections as $section): ?>
                    <article class="activity-card">
                        <div class="activity-card-header">
                            <span class="activity-icon"><i class="<?= safe($section['icon']) ?>"></i></span>
                            <div class="activity-title-wrap">
                                <h4><?= safe($section['title']) ?></h4>
                                <p class="activity-subtitle"><?= safe($section['subtitle']) ?></p>
                            </div>
                        </div>

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
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
