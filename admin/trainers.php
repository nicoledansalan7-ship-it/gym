<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php?page=login");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");
if (!$conn) {
    die("Database connection failed.");
}
mysqli_set_charset($conn, "utf8mb4");

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function refreshAssignedMembers($conn) {
    mysqli_query($conn, "
        UPDATE trainers t
        LEFT JOIN (
            SELECT trainer_id, COUNT(*) AS total_members
            FROM trainer_assignments
            GROUP BY trainer_id
        ) x ON t.id = x.trainer_id
        SET t.assigned_members = COALESCE(x.total_members, 0)
    ");
}

/* Create assignment table if it does not exist */
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS trainer_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        trainer_id INT NOT NULL,
        date_assigned DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_assignment (member_id, trainer_id),
        INDEX idx_member_id (member_id),
        INDEX idx_trainer_id (trainer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

refreshAssignedMembers($conn);

/* Flash message */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* Add trainer */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_trainer'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $assigned_members = 0;

    if ($full_name === '' || $email === '' || $phone_number === '') {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Please complete all trainer fields.'];
    } else {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO trainers (full_name, email, phone_number, assigned_members)
            VALUES (?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, "sssi", $full_name, $email, $phone_number, $assigned_members);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['flash'] = ['type' => 'success', 'text' => 'Trainer added successfully.'];
    }

    header("Location: trainers.php");
    exit();
}

/* Assign trainer */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_trainer'])) {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);

    if ($member_id <= 0 || $trainer_id <= 0) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Please select both member and trainer.'];
    } else {
        $check = mysqli_prepare($conn, "
            SELECT id
            FROM trainer_assignments
            WHERE member_id = ? AND trainer_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($check, "ii", $member_id, $trainer_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'This trainer is already assigned to this member.'];
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO trainer_assignments (member_id, trainer_id)
                VALUES (?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "ii", $member_id, $trainer_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            refreshAssignedMembers($conn);

            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Trainer assigned successfully.'];
        }

        mysqli_stmt_close($check);
    }

    header("Location: trainers.php");
    exit();
}

/* Fetch members */
$members = [];
$members_result = mysqli_query($conn, "
    SELECT id, full_name, email, membership_plan
    FROM users
    ORDER BY full_name ASC
");

if (!$members_result) {
    die("Error fetching members: " . mysqli_error($conn));
}

while ($row = mysqli_fetch_assoc($members_result)) {
    $members[] = $row;
}

/* Fetch trainers */
$trainers = [];
$trainers_result = mysqli_query($conn, "
    SELECT id, full_name, email, phone_number, assigned_members
    FROM trainers
    ORDER BY full_name ASC
");
while ($row = mysqli_fetch_assoc($trainers_result)) {
    $trainers[] = $row;
}

/* Fetch assignments */
$assignments = [];
$assignment_result = mysqli_query($conn, "
    SELECT
        ta.id,
        m.full_name AS member_name,
        m.email AS member_email,
        m.membership_plan,
        t.full_name AS trainer_name,
        t.email AS trainer_email,
        t.phone_number,
        DATE_FORMAT(ta.date_assigned, '%m/%d/%Y') AS date_assigned
    FROM trainer_assignments ta
    LEFT JOIN users m ON ta.member_id = m.id
    LEFT JOIN trainers t ON ta.trainer_id = t.id
    ORDER BY ta.date_assigned DESC, ta.id DESC
");
while ($row = mysqli_fetch_assoc($assignment_result)) {
    $assignments[] = $row;
}

$total_assignments = count($assignments);
$shown_assignments = array_slice($assignments, 0, 4);
$showing_end = min(4, $total_assignments);
?>

<?php
$navLinks = [
    'admin_dashboard.php' => ['Dashboard Overview', true, 'fas fa-tachometer-alt'], // Dashboard
    'members.php' => ['Members', false, 'fas fa-users'], // Members
    'membership_plans.php' => ['Membership Plans', false, 'fas fa-cogs'], // Membership Plans
    'attendance.php' => ['Attendance', false, 'fas fa-calendar-check'], // Attendance
    'payments.php' => ['Payments', false, 'fas fa-credit-card'], // Payments
    'trainers.php' => ['Trainers/Staffs', false, 'fas fa-chalkboard-teacher'], // Trainers/Staffs
    'equipment_list.php' => ['Equipment List', false, 'fas fa-dumbbell'], // Equipment List
    'reports.php' => ['Reports', false, 'fas fa-file-alt'], // Reports
    '../index.php?logout=true' => ['Logout', false, 'fas fa-sign-out-alt'], // Logout
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Trainers/Staffs - Vigor Gym</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- Font Awesome CDN -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
    font-family:'Poppins',sans-serif;
    background:url('bg.png') center/cover no-repeat;
    color:#fff;
    min-height:100vh
}
.admin-dashboard{
    min-height:100vh;
    display:flex;
    gap:20px;
    padding:20px;
    background:rgba(0,0,0,.55)
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
.card h3,.table-card h3,.modal-box h3{
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
.btn,.action-btn{
    border:none;
    cursor:pointer;
    color:#fff;
    border-radius:8px;
    font:600 14px 'Poppins',sans-serif;
    background:linear-gradient(180deg,#a245ff,#7c2cf4);
    box-shadow:0 4px 10px rgba(124,44,244,.35);
    transition:.25s;
    text-decoration:none
}
.btn{
    padding:10px 18px
}
.btn:hover,.action-btn:hover{opacity:.95;transform:translateY(-1px)}
.center-btn{text-align:center;margin-top:6px}

.title-actions{
    display:flex;
    justify-content:flex-start;
    margin:-4px 0 18px
}
.open-trainer-btn{
    padding:6px 12px !important;
    font-size:12px !important;
    line-height:1.2;
    width:auto;
    min-width:0;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    flex:0 0 auto;
    border-radius:6px
}

.assign-wrap{
    width:100%
}

.msg{
    padding:12px 14px;
    border-radius:8px;
    font-size:14px;
    font-weight:500;
    margin-top:-4px
}
.msg.success{
    background:rgba(34,197,94,.18);
    border:1px solid rgba(34,197,94,.28);
    color:#eafff0
}
.msg.error{
    background:rgba(239,68,68,.18);
    border:1px solid rgba(239,68,68,.28);
    color:#fff0f0
}

.table-card{padding:0;overflow:hidden}
.table-card h3{padding:18px 22px 10px}
.table-wrap{overflow-x:auto}
table{
    width:100%;
    border-collapse:collapse
}
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
.action-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:82px;
    padding:8px 14px
}
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
    align-items:center
}
.page{
    min-width:42px;
    height:32px;
    padding:0 12px;
    border-radius:7px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:rgba(124,44,244,.28);
    border:1px solid rgba(202,123,255,.16);
    font-weight:600;
    color:#fff
}
.page.active{
    background:linear-gradient(180deg,#a245ff,#7c2cf4)
}

/* modal */
.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.65);
    z-index:999;
    align-items:center;
    justify-content:center;
    padding:20px
}
.modal.show{display:flex}
.modal-box{
    width:100%;
    max-width:560px;
    padding:24px;
    position:relative
}
.close-modal{
    position:absolute;
    top:12px;
    right:14px;
    width:34px;
    height:34px;
    border:none;
    border-radius:50%;
    background:rgba(255,255,255,.08);
    color:#fff;
    font-size:20px;
    cursor:pointer
}
.close-modal:hover{
    background:rgba(171,82,242,.32)

    .modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.65);
    z-index:999;
    align-items:center;
    justify-content:center;
}

.modal.show{
    display:flex;
}

.modal-box{
    width:100%;
    max-width:420px;
    padding:24px;
    position:relative;
}

.close-modal{
    position:absolute;
    top:10px;
    right:12px;
    width:32px;
    height:32px;
    border:none;
    border-radius:50%;
    background:rgba(255,255,255,.1);
    color:#fff;
    font-size:18px;
    cursor:pointer;
}

}
.view-grid{
    display:grid;
    gap:12px
}
.view-item{
    padding:12px 14px;
    border-radius:8px;
    background:rgba(30,12,53,.58);
    border:1px solid rgba(202,123,255,.16)
}
.view-label{
    display:block;
    font-size:12px;
    color:#d9b8ff;
    margin-bottom:4px
}
.view-value{
    font-size:14px;
    font-weight:500;
    color:#fff
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
}
</style>
</head>
<body>
<div class="admin-dashboard">
    <nav class="sidebar">
        <!-- Title inside the sidebar -->
        <div class="sidebar-title-container">
            <h2 class="admin-interface-title">Admin Interface</h2>
        </div>

     
        <ul>
<?php 
                    $currentPage = basename($_SERVER['PHP_SELF']);  // Get the current page name
                    foreach ($navLinks as $href => [$label, $active, $icon]): 
                        $isActive = ($currentPage == basename($href)) ? 'active' : ''; // Check if the current page matches the href
                    ?>
                        <li>
                            <a href="<?= $href ?>" class="<?= $isActive ?>">
                                <i class="<?= $icon ?>"></i> <?= $label ?>
                            </a>
                        </li>
                <?php endforeach; ?>
        </ul>
    </nav>

    <main class="main">
        <h1>Trainers/Staffs</h1>

        <div class="title-actions">
            <button class="btn open-trainer-btn" type="button" onclick="openTrainerModal()">Add Trainer</button>
        </div>

        <?php if ($flash) { ?>
            <div class="msg <?php echo h($flash['type']); ?>"><?php echo h($flash['text']); ?></div>
        <?php } ?>

        <div class="assign-wrap">
            <div class="glass card">
                <h3>Assign Trainer to Member</h3>

                <form method="POST">
                    <div class="form-group">
                        <div class="select-wrap">
                           <select class="select" name="member_id" required>
                                <option value="">Select Member</option>

                                <?php if (!empty($members)) { ?>
                                    <?php foreach ($members as $member) { ?>
                                        <option value="<?php echo (int)$member['id']; ?>">
                                            <?php echo h($member['full_name'] . " (" . $member['email'] . ")"); ?>
                                        </option>
                                    <?php } ?>
                                <?php } else { ?>
                                    <option value="">No members found</option>
                                <?php } ?>

                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="select-wrap">
                            <select class="select" name="trainer_id" required>
                                <option value="">Select Trainer</option>
                                <?php foreach ($trainers as $trainer) { ?>
                                    <option value="<?php echo (int)$trainer['id']; ?>">
                                        <?php echo h($trainer['full_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="center-btn" style="margin-top:20px">
                        <button class="btn" type="submit" name="assign_trainer">Assign Trainer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="glass table-card">
            <h3>Assigned Trainers</h3>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Trainer</th>
                            <th>Date Assigned</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total_assignments > 0) { ?>
                            <?php foreach ($shown_assignments as $row) { ?>
                                <tr>
                                    <td><?php echo h($row['member_name'] ?: 'Unknown Member'); ?></td>
                                    <td><?php echo h($row['trainer_name'] ?: 'Unknown Trainer'); ?></td>
                                    <td><?php echo h($row['date_assigned']); ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="action-btn view-assignment-btn"
                                            data-member="<?php echo h($row['member_name']); ?>"
                                            data-member-email="<?php echo h($row['member_email']); ?>"
                                            data-membership-type="<?php echo h($row['membership_plan']); ?>"
                                            data-trainer="<?php echo h($row['trainer_name']); ?>"
                                            data-trainer-email="<?php echo h($row['trainer_email']); ?>"
                                            data-phone="<?php echo h($row['phone_number']); ?>"
                                            data-date="<?php echo h($row['date_assigned']); ?>"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <?php for ($i = 0; $i < 4; $i++) { ?>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="footer">
                <div>
                    <?php if ($total_assignments > 0) { ?>
                        Showing 1 to <?php echo $showing_end; ?> of <?php echo $total_assignments; ?> entries
                    <?php } else { ?>
                        Showing 0 to 0 of 0 entries
                    <?php } ?>
                </div>
                <div class="pagination">
                    <span class="page">Previous</span>
                    <span class="page active">1</span>
                    <span class="page">Next</span>
                </div>
            </div>
        </div>
    </main>
</div>

<div id="trainerModal" class="modal">
    <div class="glass modal-box">
        <button class="close-modal" type="button" onclick="closeTrainerModal()">×</button>

        <h3>Add Trainer</h3>

        <form method="POST">
            <div class="form-group">
                <input type="text" class="input" name="full_name" placeholder="Trainer Full Name" required>
            </div>

            <div class="form-group">
                <input type="email" class="input" name="email" placeholder="Trainer Email" required>
            </div>

            <div class="form-group">
                <input type="text" class="input" name="phone_number" placeholder="Phone Number" required>
            </div>

            <div class="center-btn">
                <button class="btn" type="submit" name="add_trainer">Add Trainer</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="glass modal-box">
        <button class="close-modal" type="button" onclick="closeViewModal()">×</button>

        <h3>Assignment Details</h3>

        <div class="view-grid">
            <div class="view-item">
                <span class="view-label">Member</span>
                <span class="view-value" id="viewMember">-</span>
            </div>

            <div class="view-item">
                <span class="view-label">Member Email</span>
                <span class="view-value" id="viewMemberEmail">-</span>
            </div>

            <div class="view-item">
                <span class="view-label">Membership Type</span>
                <span class="view-value" id="viewMembershipType">-</span>
            </div>

            <div class="view-item">
                <span class="view-label">Trainer</span>
                <span class="view-value" id="viewTrainer">-</span>
            </div>

            <div class="view-item">
                <span class="view-label">Trainer Email</span>
                <span class="view-value" id="viewTrainerEmail">-</span>
            </div>

            <div class="view-item">
                <span class="view-label">Phone Number</span>
                <span class="view-value" id="viewPhone">-</span>
            </div>

            <div class="view-item">
                <span class="view-label">Date Assigned</span>
                <span class="view-value" id="viewDate">-</span>
            </div>
        </div>
    </div>
</div>

<script>
function openTrainerModal(){
    document.getElementById('trainerModal').classList.add('show');
}

function closeTrainerModal(){
    document.getElementById('trainerModal').classList.remove('show');
}

function openViewModal(data){
    document.getElementById('viewMember').textContent = data.member || '-';
    document.getElementById('viewMemberEmail').textContent = data.memberEmail || '-';
    document.getElementById('viewMembershipType').textContent = data.membershipType || '-';
    document.getElementById('viewTrainer').textContent = data.trainer || '-';
    document.getElementById('viewTrainerEmail').textContent = data.trainerEmail || '-';
    document.getElementById('viewPhone').textContent = data.phone || '-';
    document.getElementById('viewDate').textContent = data.date || '-';
    document.getElementById('viewModal').classList.add('show');
}

function closeViewModal(){
    document.getElementById('viewModal').classList.remove('show');
}

document.querySelectorAll('.view-assignment-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
        openViewModal({
            member: this.dataset.member,
            memberEmail: this.dataset.memberEmail,
            membershipType: this.dataset.membershipType,
            trainer: this.dataset.trainer,
            trainerEmail: this.dataset.trainerEmail,
            phone: this.dataset.phone,
            date: this.dataset.date
        });
    });
});

window.addEventListener('click', function(e){
    const trainerModal = document.getElementById('trainerModal');
    const viewModal = document.getElementById('viewModal');

    if (e.target === trainerModal) closeTrainerModal();
    if (e.target === viewModal) closeViewModal();
});
</script>
</body>
</html>