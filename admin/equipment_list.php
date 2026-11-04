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



// Admin sidebar navigation links

$navLinks = [

    // Keep this order fixed on every admin page. The active page is highlighted,

    // but the navigation items never move when a page is opened.

    'admin_dashboard.php' => ['Dashboard Overview', false, 'fas fa-home'],

    'members.php' => ['Members', false, 'fas fa-users'],

    'membership_plans.php' => ['Membership Plans', false, 'fas fa-id-card'],

    'attendance.php' => ['Attendance', false, 'fas fa-calendar-check'],

    'payments.php' => ['Payments', false, 'fas fa-credit-card'],

    'trainers.php' => ['Trainers/Staffs', false, 'fas fa-user-tie'],

    'equipment_list.php' => ['Equipment List', false, 'fas fa-dumbbell'],

    'reports.php' => ['Reports', false, 'fas fa-chart-bar'],

    '../index.php?logout=true' => ['Logout', false, 'fas fa-sign-out-alt'],

];



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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght\@400;500;600;700&display=swap" rel="stylesheet">



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

            display:grid !important;

            grid-template-columns:minmax(0, 1.6fr) minmax(320px, 1fr) !important;

            gap:20px;

            align-items:start;

            width:100%;

        }

        #records-section,

        #add-section{

            display:block !important;

            min-width:0;

            width:100%;

            visibility:visible !important;

            opacity:1 !important;

        }

        .btn-wide{

            min-width:210px;

            padding:12px 20px;

        }

        .section-anchor{scroll-margin-top:24px}

        @media (max-width:900px){

            .equipment-layout{grid-template-columns:minmax(0, 1fr) !important}

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





        /* VIGOR GYM admin sidebar theme, matched to the member portal */

        :root {

            --vg-purple: #a245ff;

            --vg-purple-dark: #7c2cf4;

            --vg-purple-light: #ca7bff;

            --vg-sidebar-top: rgba(42, 26, 47, 0.94);

            --vg-sidebar-bottom: rgba(34, 5, 61, 0.96);

            --vg-glass-border: rgba(202, 123, 255, 0.22);

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
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 14px;
    padding: 13px 15px;
    color: #fff;
    font-family: 'Poppins', sans-serif;
    font-size: 16px;
    line-height: 1.35;
    font-weight: 700;
    text-align: left;
    text-decoration: none;
    border: 1px solid transparent;
    border-left: 4px solid transparent;
    border-radius: 8px;
    transition: all 0.25s ease;
}



        nav.sidebar ul li a i,
.sidebar ul li a i {
    display: inline-block;
    flex: 0 0 24px;
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



        
/* Keep navigation labels beside their icons, matching Attendance. */
body .admin-dashboard nav.sidebar ul li a span {
    display: inline-block;
    flex: 1 1 auto;
    text-align: left;
}
.main,

        .dashboard-content { flex: 1; min-width: 0; }



        .glass,

        .card,

        .table-card {

            border-color: var(--vg-glass-border);

            backdrop-filter: blur(12px);

            -webkit-backdrop-filter: blur(12px);

        }



        @media (max-width: 768px) {

            .admin-dashboard { flex-direction: column; gap: 14px; padding: 12px; }

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

            .sidebar ul li { margin: 2px 0; }

            h1 { font-size: 30px; }

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

                    // FIX: use $currentFile here. The old code used $currentPage, which

                    // overwrote the pagination page number and crashed the page below.

                    $currentFile = basename($_SERVER['PHP_SELF']);

                    foreach ($navLinks as $href => [$label, $active, $icon]):

                        $isActive = ($currentFile === basename(parse_url($href, PHP_URL_PATH))) ? 'active' : '';

                ?>

                    <li>

                        <a href="<?php echo h($href); ?>" class="<?php echo $isActive; ?>">

                            <i class="<?php echo h($icon); ?>"></i>

                            <span><?php echo h($label); ?></span>

                        </a>

                    </li>

                <?php endforeach; ?>

            </ul>

        </nav>



        <main class="main">

            <h1>Equipment List</h1>



            <?php if ($message !== ''): ?>

                <div class="msg <?php echo h($messageType); ?> glass"><?php echo h($message); ?></div>

            <?php endif; ?>



            <section class="glass card">

                <div class="equipment-layout" style="display:grid !important; grid-template-columns:minmax(0, 1.6fr) minmax(320px, 1fr) !important; gap:20px; align-items:start; width:100%;">

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



                    <div id="add-section" class="glass card section-anchor" style="display:block !important; visibility:visible !important; opacity:1 !important; width:100%; min-width:0;">

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

</body>

</html>
