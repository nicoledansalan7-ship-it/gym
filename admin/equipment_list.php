<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
if (!$conn) {
    die("Database connection failed.");
}

$validConditions = ['Excellent', 'Good', 'Average', 'Poor'];
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'add' ? 'add' : 'records';
$message = '';
$messageType = '';

// Function to sanitize and escape output
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Function to format display date
function formatDisplayDate($date)
{
    $timestamp = strtotime($date);
    return $timestamp ? date('m/d/Y', $timestamp) : $date;
}


// Handle adding new equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

if ($action === 'add_equipment') {
    $name = trim($_POST['equipment_name'] ?? '');
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $condition = isset($_POST['condition']) ? trim($_POST['condition']) : '';
    $purchaseDate = trim($_POST['purchase_date'] ?? '');

    if ($name === '' || $quantity < 1 || $condition === '' || $purchaseDate === '') {
        $message = 'Please complete all fields correctly.';
        $messageType = 'error';
        $activeTab = 'add';
    } else {
        // Check if the equipment already exists
        $stmtCheck = $conn->prepare("SELECT id FROM equipment WHERE name = ? AND purchase_date = ?");
        $stmtCheck->bind_param("ss", $name, $purchaseDate);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows > 0) {
            $message = 'This equipment already exists.';
            $messageType = 'error';
            $activeTab = 'add';
        } else {
            // Insert new equipment
            $stmt = $conn->prepare("INSERT INTO equipment (name, quantity, condition_status, purchase_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siss", $name, $quantity, $condition, $purchaseDate);

            if ($stmt->execute()) {
                header("Location: equipment_list.php?success=1");
                exit();
            } else {
                $message = 'Failed to add equipment.';
                $messageType = 'error';
            }

            $stmt->close();
        }

        $stmtCheck->close();
    }
}
}
// Fetch equipment records from the database
$items = []; // initialize as empty array to avoid undefined variable

$result = $conn->query("SELECT id, name, quantity, condition_status, purchase_date FROM equipment");

if ($result) { // check query success
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
} else {
    // Optional: log SQL errors for debugging
    error_log("Equipment query failed: " . $conn->error);
}

// Pagination logic
$perPage = 4;
$totalItems = count($items);
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $perPage;
$currentItems = array_slice($items, $offset, $perPage);
$showingFrom = $totalItems > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalItems);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- Font Awesome CDN -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{
            font-family:'Poppins',sans-serif;
            background:url('bg.png') center/cover no-repeat;
            color:#fff;
            min-height:100vh;
        }
        .admin-dashboard{
            min-height:100vh;
            display:flex;
            gap:20px;
            padding:20px;
            background:rgba(0,0,0,.55);
        }

        .admin-interface-title {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            text-align: center;
            margin-bottom: 20px;  /* Space between title and first navigation item */
            padding: 10px;
            border-bottom: 2px solid #ca7bff; /* Optional: adds a border to separate title from links */
        }

        .sidebar-title-container {
            padding: 20px 0;  /* Add padding to give space inside the sidebar */
            background: rgba(42, 26, 47, 0.88);  /* Optional: make it match the sidebar's background */
            text-align: center;
        }

        .sidebar{
            width:250px;
            padding:20px;
            border-radius:10px;
            flex-shrink:0;
            position:sticky;
            top:20px;
            height:calc(100vh - 40px);
            background:linear-gradient(180deg,rgba(42,26,47,.9),rgba(34,5,61,.9));
            box-shadow:0 4px 8px rgba(0,0,0,.4);
            backdrop-filter:blur(8px);
            -webkit-backdrop-filter:blur(8px)
        }
        .sidebar ul{list-style:none}
        .sidebar li{margin:15px 0}
        .sidebar a{
            display:block;
            padding:10px;
            border-radius:5px;
            color:#fff;
            text-decoration:none;
            font-size:16px;
            font-weight:600;
            border-left:5px solid transparent;
            transition:.25s
        }
        .sidebar a:hover,.sidebar a.active{
            background:rgba(171,82,242,.32);
            border-left-color:#ca7bff;
            padding-left:15px
        }
        .main{
            flex:1;
            min-width:0;
            display:flex;
            flex-direction:column;
            gap:18px
        }
        h1{
            font-size:36px;
            font-weight:700;
            margin-bottom:6px;
            letter-spacing:.5px;
            text-shadow:0 2px 8px rgba(0,0,0,.35)
        }
        .glass{
            background:rgba(44,27,83,.22);
            border:1px solid rgba(202,123,255,.18);
            border-radius:12px;
            box-shadow:0 6px 18px rgba(0,0,0,.2);
            backdrop-filter:blur(12px);
            -webkit-backdrop-filter:blur(12px)
        }
        .card{padding:22px}
        .card h3,.table-card h3{
            font-size:18px;
            margin-bottom:18px;
            color:#f3e7ff
        }
        .form-group{margin-bottom:14px}
        .select-wrap{position:relative}
        .input,.select{
            width:100%;
            padding:13px 16px;
            border-radius:8px;
            outline:none;
            border:1px solid rgba(202,123,255,.18);
            background:rgba(30,12,53,.78);
            color:#fff;
            font:500 15px 'Poppins',sans-serif
        }
        .input::placeholder,.select{color:rgba(255,255,255,.75)}
        .select{
            appearance:none;
            -webkit-appearance:none;
            -moz-appearance:none;
            cursor:pointer;
            padding-right:44px
        }
        .select-wrap:after{
            content:"▼";
            position:absolute;
            right:14px;
            top:50%;
            transform:translateY(-50%);
            font-size:11px;
            color:#d7b5ff;
            pointer-events:none
        }
        .btn,.tab-link,.page-link{
            border:none;
            cursor:pointer;
            color:#fff;
            border-radius:8px;
            font:600 14px 'Poppins',sans-serif;
            background:linear-gradient(180deg,#a245ff,#7c2cf4);
            box-shadow:0 4px 10px rgba(124,44,244,.35);
            transition:.25s;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }
        .btn{padding:10px 18px}
        .btn:hover,.tab-link:hover,.page-link:hover{opacity:.95;transform:translateY(-1px)}
        .center-btn{text-align:center;margin-top:6px}
        .msg{
            padding:12px 14px;
            border-radius:8px;
            font-size:14px;
            font-weight:500;
            margin-top:-4px;
        }
        .msg.success{
            background:rgba(34,197,94,.18);
            border:1px solid rgba(34,197,94,.28);
            color:#eafff0;
        }
        .msg.error{
            background:rgba(239,68,68,.18);
            border:1px solid rgba(239,68,68,.28);
            color:#fff0f0;
        }
        .table-card{padding:0;overflow:hidden}
        .table-card h3{padding:18px 22px 10px;margin-bottom:0}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th,td{
            padding:14px 16px;
            text-align:left;
            white-space:nowrap;
            border-bottom:1px solid rgba(255,255,255,.08);
            height:54px
        }
        th{
            background:rgba(50,21,83,.55);
            color:#f4e9ff;
            font-size:14px;
            font-weight:700
        }
        td{
            background:rgba(255,255,255,.03);
            font-size:14px;
            color:#fff
        }
        tbody tr:nth-child(even) td{background:rgba(255,255,255,.05)}
        tbody tr:hover td{background:rgba(202,123,255,.10)}
        .footer{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            padding:14px 16px;
            color:#ead9ff;
            font-size:14px;
            background:rgba(30,12,53,.55)
        }
        .pagination{
            display:flex;
            gap:8px;
            align-items:center;
            flex-wrap:wrap;
        }
        .page-link{
            min-width:42px;
            height:32px;
            padding:0 12px;
            background:rgba(124,44,244,.28);
            border:1px solid rgba(202,123,255,.16);
            box-shadow:none;
        }
        .page-link.active{
            background:linear-gradient(180deg,#a245ff,#7c2cf4);
            box-shadow:0 4px 10px rgba(124,44,244,.35);
        }
        .page-link.disabled{
            opacity:.45;
            pointer-events:none;
        }
        .toolbar{
            display:flex;
            gap:12px;
            align-items:center;
            flex-wrap:wrap;
            padding:10px 12px;
        }
        .tab-link{
            background:rgba(96,58,132,.48);
            box-shadow:none;
            padding:10px 16px;
        }
        .tab-link.active{
            background:linear-gradient(180deg,#a245ff,#7c2cf4);
            box-shadow:0 4px 10px rgba(124,44,244,.35);
        }
        .equipment-layout{
            display:grid;
            grid-template-columns:1.65fr 1fr;
            gap:20px;
            align-items:start;
        }
        .btn-wide{
            min-width:210px;
            padding:12px 20px;
        }
        .section-anchor{scroll-margin-top:24px}
        @media (max-width:1100px){
            .equipment-layout{grid-template-columns:1fr}
        }
        @media (max-width:768px){
            .admin-dashboard{flex-direction:column}
            .sidebar{
                width:100%;
                height:auto;
                position:relative;
                top:0
            }
            h1{font-size:32px}
            .toolbar{padding:12px}
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
    <li><a href="admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard Overview</a></li>
    <li><a href="members.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'members.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Members</a></li>
    <li><a href="membership_plans.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'membership_plans.php' ? 'active' : ''; ?>"><i class="fas fa-cogs"></i> Membership Plans</a></li>
    <li><a href="attendance.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : ''; ?>"><i class="fas fa-calendar-check"></i> Attendance</a></li>
    <li><a href="payments.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>"><i class="fas fa-credit-card"></i> Payments</a></li>
    <li><a href="trainers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'trainers.php' ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Trainers/Staffs</a></li>
    <li><a href="equipment_list.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'equipment_list.php' ? 'active' : ''; ?>"><i class="fas fa-dumbbell"></i> Equipment List</a></li>
    <li><a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-file-alt"></i> Reports</a></li>
    <li><a href="../index.php?logout=true" class="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
        </nav>

        <main class="main">
            <h1>Equipment List</h1>

            <?php if ($message !== ''): ?>
                <div class="msg <?php echo h($messageType); ?> glass"><?php echo h($message); ?></div>
            <?php endif; ?>

            <section class="glass card">
                <div class="equipment-layout">
                    <div id="records-section" class="glass table-card section-anchor">
                        <h3>Equipment Records</h3>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Quantity</th>
                                        <th>Condition</th>
                                        <th>Purchase Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($currentItems)): ?>
                                        <tr>
                                            <td colspan="4">No equipment records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($currentItems as $item): ?>
                                            <tr>
                                                <td><?php echo h($item['name']); ?></td>
                                                <td><?php echo h($item['quantity']); ?></td>
                                                <td><?php echo isset($item['condition_status']) ? h($item['condition_status']) : 'N/A'; ?></td>
                                                <td><?php echo h(formatDisplayDate($item['purchase_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="footer">
                            <span>Showing <?php echo $showingFrom; ?> to <?php echo $showingTo; ?> of <?php echo $totalItems; ?> entries</span>
                            <div class="pagination">
                                <a class="page-link <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" href="?tab=records&page=<?php echo max(1, $currentPage - 1); ?>#records-section">Previous</a>
                                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                    <a class="page-link <?php echo $page === $currentPage ? 'active' : ''; ?>" href="?tab=records&page=<?php echo $page; ?>#records-section"><?php echo $page; ?></a>
                                <?php endfor; ?>
                                <a class="page-link <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" href="?tab=records&page=<?php echo min($totalPages, $currentPage + 1); ?>#records-section">Next</a>
                            </div>
                        </div>
                    </div>

                    <div id="add-section" class="glass card section-anchor">
                        <h3>Add Equipment</h3>
                        <form action="equipment_list.php" method="post">
                            <input type="hidden" name="action" value="add_equipment">

                            <div class="form-group">
                                <input type="text" name="equipment_name" class="input" placeholder="Equipment Name" required>
                            </div>

                            <div class="form-group">
                                <input type="number" name="quantity" class="input" placeholder="Quantity" min="1" required>
                            </div>

                            <div class="form-group select-wrap">
                                <select name="condition" class="select" required>
                                    <option value="" selected disabled>Select Condition</option>
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good">Good</option>
                                    <option value="Average">Average</option>
                                    <option value="Poor">Poor</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <input type="date" name="purchase_date" class="input" required>
                            </div>

                            <div class="center-btn">
                                <button type="submit" class="btn btn-wide">Add Equipment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
<script>
    // Wait until the DOM is fully loaded
    document.addEventListener("DOMContentLoaded", function() {
        const msg = document.querySelector('.msg');
        if (msg) {
            // Set timeout to hide the message after 3 seconds (3000ms)
            setTimeout(() => {
                msg.style.transition = "opacity 0.5s ease";
                msg.style.opacity = "0";

                // Remove the element from DOM after transition
                setTimeout(() => msg.remove(), 500);
            }, 3000); // Change 3000 to 5000 if you want 5 seconds
        }
    });
</script>
</html>