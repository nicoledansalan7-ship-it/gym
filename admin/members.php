<?php
session_start();
mysqli_report(MYSQLI_REPORT_OFF);

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
if (!$conn) die("Database connection failed.");

$msg = "";
$type = "";
$showAddModal = false;
$showEditModal = false;
$perPage = 5;

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean($conn, $value) {
    return mysqli_real_escape_string($conn, trim((string) $value));
}

function columnExists($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($check && mysqli_num_rows($check) > 0);
}

function ensureUsersTable($conn) {
    $createTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) DEFAULT NULL,
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'member',
            membership_plan VARCHAR(100) NOT NULL DEFAULT 'Standard',
            status VARCHAR(20) NOT NULL DEFAULT 'Active',
            member_since DATE DEFAULT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            deactivation_reason VARCHAR(50) NOT NULL DEFAULT 'none',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    mysqli_query($conn, $createTable);

    $columns = [
        'email' => "ALTER TABLE users ADD COLUMN email VARCHAR(150) DEFAULT NULL",
        'role' => "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'member'",
        'membership_plan' => "ALTER TABLE users ADD COLUMN membership_plan VARCHAR(100) NOT NULL DEFAULT 'Standard'",
        'status' => "ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'",
        'member_since' => "ALTER TABLE users ADD COLUMN member_since DATE DEFAULT NULL",
        'failed_attempts' => "ALTER TABLE users ADD COLUMN failed_attempts INT NOT NULL DEFAULT 0",
        'is_active' => "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        'deactivation_reason' => "ALTER TABLE users ADD COLUMN deactivation_reason VARCHAR(50) NOT NULL DEFAULT 'none'"
    ];

    foreach ($columns as $column => $query) {
        if (!columnExists($conn, 'users', $column)) {
            mysqli_query($conn, $query);
        }
    }
}

ensureUsersTable($conn);

$addFullName = '';
$addEmail = '';
$addUsername = '';
$addMembership = 'Standard';

$editId = 0;
$editFullName = '';
$editEmail = '';
$editUsername = '';
$editMembership = 'Standard';

if (isset($_POST['add_member'])) {
    $addFullName = trim($_POST['full_name'] ?? '');
    $addEmail = trim($_POST['email'] ?? '');
    $addUsername = trim($_POST['username'] ?? '');
    $addPassword = trim($_POST['password'] ?? '');
    $addMembership = trim($_POST['membership_plan'] ?? 'Standard');
    $memberSince = date('Y-m-d');
    $showAddModal = true;

    if ($addFullName === '' || $addEmail === '' || $addUsername === '' || $addPassword === '') {
        $msg = 'Please complete all member fields.';
        $type = 'error';
    } elseif (!filter_var($addEmail, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid email address.';
        $type = 'error';
    } else {
        $fullName = clean($conn, $addFullName);
        $email = clean($conn, $addEmail);
        $username = clean($conn, $addUsername);
        $membershipPlan = clean($conn, $addMembership);
        $status = 'Active';
        $isActive = 1;
        $deactivationReason = 'none';

        $checkUser = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' LIMIT 1");

        if ($checkUser && mysqli_num_rows($checkUser) > 0) {
            $msg = 'Username already exists. Please choose another username.';
            $type = 'error';
        } else {
            $hashedPassword = password_hash($addPassword, PASSWORD_DEFAULT);
            $hashedPasswordSafe = mysqli_real_escape_string($conn, $hashedPassword);

            $insert = mysqli_query(
                $conn,
                "INSERT INTO users (full_name, email, username, password, role, membership_plan, status, member_since, failed_attempts, is_active, deactivation_reason)
                 VALUES ('$fullName', '$email', '$username', '$hashedPasswordSafe', 'member', '$membershipPlan', '$status', '$memberSince', 0, $isActive, '$deactivationReason')"
            );

            if ($insert) {
                $msg = 'New member added successfully.';
                $type = 'success';
                $showAddModal = false;
                $addFullName = '';
                $addEmail = '';
                $addUsername = '';
                $addMembership = 'Standard';
            } else {
                $msg = 'Unable to add member right now. Please check your users table setup.';
                $type = 'error';
            }
        }
    }
}

if (isset($_POST['toggle_member_status'])) {
    $memberId = (int) ($_POST['member_id'] ?? 0);
    $toggleTo = trim($_POST['toggle_to'] ?? '');

    if ($memberId > 0 && in_array($toggleTo, ['Active', 'Deactivated'], true)) {
        if ($toggleTo === 'Active') {
            $query = "UPDATE users 
                      SET status='Active', is_active=1, deactivation_reason='none', failed_attempts=0
                      WHERE id=$memberId AND role='member'";
            mysqli_query($conn, $query);
            $msg = 'Member activated successfully.';
        } else {
            $query = "UPDATE users 
                      SET status='Deactivated', is_active=0, deactivation_reason='admin'
                      WHERE id=$memberId AND role='member' AND id != 1";
            mysqli_query($conn, $query);
            $msg = 'Member deactivated successfully.';
        }

        $type = 'success';
    }
}

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    if ($editId > 0) {
        $editQuery = mysqli_query($conn, "SELECT * FROM users WHERE id=$editId AND role='member' LIMIT 1");
        if ($editQuery && mysqli_num_rows($editQuery) > 0) {
            $editUser = mysqli_fetch_assoc($editQuery);
            $editFullName = $editUser['full_name'] ?? '';
            $editEmail = $editUser['email'] ?? '';
            $editUsername = $editUser['username'] ?? '';
            $editMembership = $editUser['membership_plan'] ?? 'Standard';
            $showEditModal = true;
        }
    }
}

if (isset($_POST['save_member_changes'])) {
    $editId = (int) ($_POST['member_id'] ?? 0);
    $editFullName = trim($_POST['edit_full_name'] ?? '');
    $editEmail = trim($_POST['edit_email'] ?? '');
    $editUsername = trim($_POST['edit_username'] ?? '');
    $editPassword = trim($_POST['edit_password'] ?? '');
    $editMembership = trim($_POST['edit_membership_plan'] ?? 'Standard');
    $showEditModal = true;

    if ($editId <= 0 || $editFullName === '' || $editEmail === '' || $editUsername === '') {
        $msg = 'Please complete all required edit fields.';
        $type = 'error';
    } elseif (!filter_var($editEmail, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid email address.';
        $type = 'error';
    } else {
        $fullName = clean($conn, $editFullName);
        $email = clean($conn, $editEmail);
        $username = clean($conn, $editUsername);
        $membershipPlan = clean($conn, $editMembership);

        $duplicateCheck = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' AND id != $editId LIMIT 1");

        if ($duplicateCheck && mysqli_num_rows($duplicateCheck) > 0) {
            $msg = 'Username already exists. Please choose another username.';
            $type = 'error';
        } else {
            $updateSql = "UPDATE users 
                          SET full_name='$fullName', email='$email', username='$username', membership_plan='$membershipPlan'";

            if ($editPassword !== '') {
                $hashedPassword = password_hash($editPassword, PASSWORD_DEFAULT);
                $hashedPasswordSafe = mysqli_real_escape_string($conn, $hashedPassword);
                $updateSql .= ", password='$hashedPasswordSafe'";
            }

            $updateSql .= " WHERE id=$editId AND role='member'";

            if (mysqli_query($conn, $updateSql)) {
                $msg = 'Member updated successfully.';
                $type = 'success';
                $showEditModal = false;
                $editId = 0;
                $editFullName = '';
                $editEmail = '';
                $editUsername = '';
                $editMembership = 'Standard';
            } else {
                $msg = 'Unable to update member right now.';
                $type = 'error';
            }
        }
    }
}

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$currentPage = max(1, (int) ($_GET['p'] ?? 1));

$where = ["role = 'member'"];

if ($search !== '') {
    $searchSafe = clean($conn, $search);
    $where[] = "(full_name LIKE '%$searchSafe%' OR email LIKE '%$searchSafe%' OR username LIKE '%$searchSafe%')";
}

if ($statusFilter === 'active') {
    $where[] = "is_active = 1 AND status = 'Active'";
} elseif ($statusFilter === 'deactivated') {
    $where[] = "is_active = 0 AND status = 'Deactivated'";
}

$whereSql = implode(' AND ', $where);

$countQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE $whereSql");
$countRow = $countQuery ? mysqli_fetch_assoc($countQuery) : ['total' => 0];
$totalMembers = (int) ($countRow['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalMembers / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

$membersResult = mysqli_query(
    $conn,
    "SELECT id, full_name, email, username, member_since, membership_plan, status, is_active
     FROM users
     WHERE $whereSql
     ORDER BY id DESC
     LIMIT $offset, $perPage"
);

$memberRows = [];
if ($membersResult) {
    while ($row = mysqli_fetch_assoc($membersResult)) {
        $memberRows[] = $row;
    }
}

$activeCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='member' AND is_active=1 AND status='Active'");
$deactivatedCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='member' AND is_active=0 AND status='Deactivated'");
$allCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='member'");

$activeCount = $activeCountResult ? (int) (mysqli_fetch_assoc($activeCountResult)['total'] ?? 0) : 0;
$deactivatedCount = $deactivatedCountResult ? (int) (mysqli_fetch_assoc($deactivatedCountResult)['total'] ?? 0) : 0;
$allCount = $allCountResult ? (int) (mysqli_fetch_assoc($allCountResult)['total'] ?? 0) : 0;

function buildMembersUrl($search, $statusFilter, $page) {
    $params = [];
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($statusFilter !== 'all') {
        $params['status'] = $statusFilter;
    }
    $params['p'] = $page;
    return 'members.php?' . http_build_query($params);
}
?>

<?php
$navLinks = [
    'admin_dashboard.php' => ['Dashboard Overview', false, 'fas fa-tachometer-alt'],
    'members.php' => ['Members', true, 'fas fa-users'],
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
    <title>Gym Management Dashboard</title>

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
            margin-right: 0;
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

        .overview {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .overview-item {
            background: rgba(92, 61, 176, 0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(202, 123, 255, 0.25);
            padding: 18px;
            border-radius: 14px;
            width: calc(33.333% - 10px);
            text-align: center;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
            font-size: 14px;
        }

        .overview-item h3 {
            color: #e9c8ff;
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 600;
        }

        .overview-item p {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
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

        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .filters-form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            flex: 1;
        }

        .search-box {
            padding: 12px 14px;
            width: 320px;
            max-width: 100%;
            border-radius: 8px;
            border: 1px solid rgba(202, 123, 255, 0.3);
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
            color: #fff;
            font-size: 14px;
            outline: none;
        }

        .search-box::placeholder {
            color: rgba(255, 255, 255, 0.75);
        }

        .filter-status {
            padding: 10px 12px;
            border-radius: 8px;
            background: rgba(124, 77, 255, 0.22);
            backdrop-filter: blur(8px);
            color: #fff;
            border: 1px solid rgba(202, 123, 255, 0.3);
            font-size: 14px;
            outline: none;
            min-width: 170px;
        }

        .filter-status option {
            color: #000;
        }

        .action-btn,
        .submit-btn,
        .secondary-btn,
        .mini-btn {
            padding: 10px 16px;
            background: linear-gradient(180deg, #a245ff, #7c2cf4);
            color: white;
            border: 1px solid rgba(202, 123, 255, 0.25);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(124, 44, 244, 0.35);
            font-family: inherit;
        }

        .secondary-btn {
            background: rgba(255, 255, 255, 0.08);
            box-shadow: none;
        }

        .mini-btn {
            padding: 8px 12px;
            font-size: 13px;
            box-shadow: none;
        }

        .danger-btn {
            background: linear-gradient(180deg, #c45dff, #8d33f7);
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: rgba(44, 27, 83, 0.14);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 12px;
            overflow: hidden;
        }

        table th,
        table td {
            padding: 14px 12px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 15px;
        }

        table th {
            background: rgba(124, 77, 255, 0.28);
            backdrop-filter: blur(10px);
            color: #f5eaff;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        table td {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
            font-weight: 500;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
            vertical-align: middle;
        }

        table tbody tr:hover td {
            background: rgba(202, 123, 255, 0.12);
            transition: 0.25s ease;
        }

        .password-mask {
            letter-spacing: 2px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-active {
            background: rgba(56, 189, 248, 0.16);
            border: 1px solid rgba(56, 189, 248, 0.28);
        }

        .status-deactivated {
            background: rgba(244, 114, 182, 0.16);
            border: 1px solid rgba(244, 114, 182, 0.28);
        }

        .actions-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty-row {
            padding: 30px 12px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .page-link {
            color: white;
            font-weight: 600;
            text-decoration: none;
            padding: 8px 14px;
            background: rgba(124, 77, 255, 0.25);
            border: 1px solid rgba(202, 123, 255, 0.25);
            border-radius: 6px;
        }

        .page-link.disabled {
            opacity: .45;
            pointer-events: none;
        }

        .msg {
            padding: 12px 16px;
            font-size: 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.05);
            background: rgba(255,255,255,0.03);
            margin-bottom: 14px;
        }

        .msg.success {
            color: #f5deff;
            box-shadow: 0 0 10px rgba(194, 97, 255, 0.08);
        }

        .msg.error {
            color: #ffb0d2;
            box-shadow: 0 0 10px rgba(255, 120, 180, 0.06);
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 999;
        }

        .modal.show {
            display: flex;
        }

        .modal-card {
            width: 100%;
            max-width: 620px;
            background: linear-gradient(180deg, rgba(35, 10, 59, 0.96), rgba(20, 5, 35, 0.98));
            border: 1px solid rgba(202, 123, 255, 0.22);
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.45);
            padding: 24px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .modal-header h2 {
            font-size: 24px;
            color: #f5eaff;
        }

        .close-btn {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 1px solid rgba(202, 123, 255, 0.22);
            background: rgba(255,255,255,0.06);
            color: #fff;
            font-size: 20px;
            cursor: pointer;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: #f3ddff;
            font-size: 14px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            height: 44px;
            padding: 10px 14px;
            border: 1px solid rgba(136, 58, 214, 0.72);
            border-radius: 8px;
            background: rgba(18, 5, 30, 0.84);
            color: #ffffff;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            outline: none;
        }

        .form-group select option {
            color: #000;
        }

        .modal-note {
            font-size: 12px;
            color: #d7b5ff;
            margin-top: -2px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        @media (max-width: 900px) {
            .overview-item {
                width: calc(50% - 10px);
            }

            .form-grid {
                grid-template-columns: 1fr;
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

            .top-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box,
            .filter-status,
            .action-btn {
                width: 100%;
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
    width: 210px;
    min-width: 210px;
    flex: 0 0 210px;
    height: calc(100vh - 40px);
    min-height: 600px;
    position: sticky;
    top: 20px;
    padding: 16px;
    overflow-y: auto;
    border: 1px solid rgba(202, 123, 255, 0.12);
    border-radius: 12px;
    background: linear-gradient(180deg, var(--vg-sidebar-top) 0%, var(--vg-sidebar-bottom) 100%);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.sidebar-brand {
    min-height: 74px;
    margin: 0 0 12px;
    padding: 12px 8px 10px;
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
    font-size: 17px;
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
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.sidebar-profile {
    min-height: 58px;
    margin-bottom: 14px;
    padding: 9px 10px;
    display: flex;
    align-items: center;
    gap: 9px;
    border: 1px solid rgba(202, 123, 255, 0.30);
    border-radius: 9px;
    background: rgba(171, 82, 242, 0.16);
}

.sidebar-avatar {
    width: 34px;
    height: 34px;
    min-width: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: #fff;
    font-size: 15px;
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
    font-size: 10px;
    font-weight: 800;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-profile-role {
    margin-top: 3px;
    color: #eadcff;
    font-size: 9px;
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
    margin: 7px 0;
}

nav.sidebar ul li a,
.sidebar ul li a {
    min-height: 43px;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 11px;
    color: #fff;
    font-size: 11px;
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
    width: 16px;
    min-width: 16px;
    color: #d7a2ff;
    font-size: 13px;
    text-align: center;
}

nav.sidebar ul li a:hover,
nav.sidebar ul li a.active,
.sidebar ul li a:hover,
.sidebar ul li a.active {
    color: #fff;
    background: linear-gradient(90deg, rgba(171, 82, 242, 0.34), rgba(171, 82, 242, 0.10));
    border-left-color: #ca7bff;
    padding-left: 14px;
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
        <h1>Members</h1>

        <div class="overview">
            <div class="overview-item">
                <h3>Total Members</h3>
                <p><?php echo $allCount; ?></p>
            </div>
            <div class="overview-item">
                <h3>Active Members</h3>
                <p><?php echo $activeCount; ?></p>
            </div>
            <div class="overview-item">
                <h3>Deactivated Members</h3>
                <p><?php echo $deactivatedCount; ?></p>
            </div>
        </div>

        <div class="members-list">
            <?php if ($msg !== ''): ?>
                <div class="msg <?php echo h($type); ?>"><?php echo h($msg); ?></div>
            <?php endif; ?>

            <div class="top-actions">
                <form method="get" class="filters-form">
                    <input type="text" name="search" placeholder="Search Members..." class="search-box" value="<?php echo h($search); ?>">

                    <select name="status" class="filter-status" onchange="this.form.submit()">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Members</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active Members</option>
                        <option value="deactivated" <?php echo $statusFilter === 'deactivated' ? 'selected' : ''; ?>>Deactivated Members</option>
                    </select>
                </form>

                <button type="button" class="action-btn" id="openAddMember">Add New Member</button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Member Since</th>
                            <th>Membership</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($memberRows)): ?>
                            <tr>
                                <td colspan="8" class="empty-row">No members found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($memberRows as $member): ?>
                                <?php $rowStatus = ((int) $member['is_active'] === 1) ? 'Active' : 'Deactivated'; ?>
                                <tr>
                                    <td><?php echo h($member['full_name'] ?? ''); ?></td>
                                    <td><?php echo h($member['email'] ?? ''); ?></td>
                                    <td><?php echo h($member['username'] ?? ''); ?></td>
                                    <td><span class="password-mask">••••••••</span></td>
                                    <td><?php echo h($member['member_since'] ?? ''); ?></td>
                                    <td><?php echo h($member['membership_plan'] ?? 'Standard'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $rowStatus === 'Active' ? 'status-active' : 'status-deactivated'; ?>">
                                            <?php echo h($rowStatus); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <a class="mini-btn" href="members.php?edit=<?php echo (int) $member['id']; ?>">Edit</a>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="member_id" value="<?php echo (int) $member['id']; ?>">
                                                <input type="hidden" name="toggle_to" value="<?php echo $rowStatus === 'Active' ? 'Deactivated' : 'Active'; ?>">
                                                <button type="submit" name="toggle_member_status" class="mini-btn danger-btn">
                                                    <?php echo $rowStatus === 'Active' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <a class="page-link <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" href="<?php echo h(buildMembersUrl($search, $statusFilter, max(1, $currentPage - 1))); ?>">Previous</a>
                <span><?php echo $currentPage; ?> / <?php echo $totalPages; ?></span>
                <a class="page-link <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo h(buildMembersUrl($search, $statusFilter, min($totalPages, $currentPage + 1))); ?>">Next</a>
            </div>
        </div>
    </div>
</div>

<div class="modal <?php echo $showAddModal ? 'show' : ''; ?>" id="addMemberModal">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Add New Member</h2>
            <button type="button" class="close-btn" id="closeAddMember">&times;</button>
        </div>

        <form method="post">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo h($addFullName); ?>" required>
                </div>

                <div class="form-group full">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo h($addEmail); ?>" required>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo h($addUsername); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="text" id="password" name="password" required>
                </div>

                <div class="form-group full">
                    <label for="membership_plan">Membership</label>
                    <select id="membership_plan" name="membership_plan">
                        <option value="Standard" <?php echo $addMembership === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="Monthly" <?php echo $addMembership === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="Quarterly" <?php echo $addMembership === 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="Annual" <?php echo $addMembership === 'Annual' ? 'selected' : ''; ?>>Annual</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="secondary-btn" id="cancelAddMember">Cancel</button>
                <button type="submit" name="add_member" class="submit-btn">Save Member</button>
            </div>
        </form>
    </div>
</div>

<div class="modal <?php echo $showEditModal ? 'show' : ''; ?>" id="editMemberModal">
    <div class="modal-card">
        <div class="modal-header">
            <h2>Edit Member</h2>
            <button type="button" class="close-btn" id="closeEditMember">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="member_id" value="<?php echo (int) $editId; ?>">

            <div class="form-grid">
                <div class="form-group full">
                    <label for="edit_full_name">Full Name</label>
                    <input type="text" id="edit_full_name" name="edit_full_name" value="<?php echo h($editFullName); ?>" required>
                </div>

                <div class="form-group full">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="edit_email" value="<?php echo h($editEmail); ?>" required>
                </div>

                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" name="edit_username" value="<?php echo h($editUsername); ?>" required>
                </div>

                <div class="form-group">
                    <label for="edit_password">New Password</label>
                    <input type="text" id="edit_password" name="edit_password" placeholder="Leave blank to keep current password">
                    <div class="modal-note">For security, saved passwords are not shown here.</div>
                </div>

                <div class="form-group full">
                    <label for="edit_membership_plan">Membership</label>
                    <select id="edit_membership_plan" name="edit_membership_plan">
                        <option value="Standard" <?php echo $editMembership === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="Monthly" <?php echo $editMembership === 'Monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="Quarterly" <?php echo $editMembership === 'Quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="Annual" <?php echo $editMembership === 'Annual' ? 'selected' : ''; ?>>Annual</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="secondary-btn" id="cancelEditMember">Cancel</button>
                <button type="submit" name="save_member_changes" class="submit-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const currentPage = window.location.pathname.split("/").pop();
    const navLinks = document.querySelectorAll("nav.sidebar a");

    navLinks.forEach(function(link) {
        if (link.getAttribute("href").includes(currentPage)) {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    });

    const addModal = document.getElementById('addMemberModal');
    const editModal = document.getElementById('editMemberModal');
    const openAddBtn = document.getElementById('openAddMember');
    const closeAddBtn = document.getElementById('closeAddMember');
    const cancelAddBtn = document.getElementById('cancelAddMember');
    const closeEditBtn = document.getElementById('closeEditMember');
    const cancelEditBtn = document.getElementById('cancelEditMember');

    if (openAddBtn) {
        openAddBtn.addEventListener('click', function () {
            openModal(addModal);
        });
    }

    if (closeAddBtn) {
        closeAddBtn.addEventListener('click', function () {
            closeModal(addModal);
        });
    }

    if (cancelAddBtn) {
        cancelAddBtn.addEventListener('click', function () {
            closeModal(addModal);
        });
    }

    if (closeEditBtn) {
        closeEditBtn.addEventListener('click', function () {
            closeModal(editModal);
        });
    }

    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', function () {
            closeModal(editModal);
        });
    }

    function openModal(modal) {
        if (modal) modal.classList.add('show');
    }

    function closeModal(modal) {
        if (modal) modal.classList.remove('show');
    }
});
</script>

</body>
</html>