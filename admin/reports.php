<?php

session_start();



// Database connection

$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");

if (!$conn) die("Database connection failed.");



// Get selected report

$report = $_GET['report'] ?? '';

$allowedReports = ['membership','income','attendance'];

if(!in_array($report,$allowedReports,true)) $report='';



// Initialize arrays

$membershipData = [];

$incomeData = [];

$attendanceData = [];



// === Helper functions ===

if(!function_exists('h')){

    function h($value){ return htmlspecialchars((string)$value, ENT_QUOTES,'UTF-8'); }

}

if(!function_exists('money')){

    function money($value){ return 'P'.number_format((float)$value,2); }

}

if(!function_exists('iconSvg')){

    function iconSvg($type){

        if($type==='membership') return '<svg viewBox="0 0 64 64"><circle cx="20" cy="21" r="7"></circle><circle cx="32" cy="17" r="9"></circle><circle cx="45" cy="21" r="7"></circle></svg>';

        if($type==='income') return '<svg viewBox="0 0 64 64"><path d="M31 10a22 22 0 1 0 22 22H31V10z"></path></svg>';

        return '<svg viewBox="0 0 64 64"><rect x="18" y="10" width="28" height="42" rx="4"></rect></svg>';

    }

}



// === CSV Export ===

function exportCsv($report,$membershipData,$incomeData,$attendanceData){

    header('Content-Type:text/csv; charset=utf-8');

    header('Content-Disposition:attachment; filename='.$report.'_report.csv');

    $output = fopen('php://output','w');



    if($report==='membership'){

        fputcsv($output,['Plan','Members','Revenue']);

        foreach($membershipData as $row) fputcsv($output,[$row['plan'],$row['members'],$row['revenue']]);

    }elseif($report==='income'){

        fputcsv($output,['Month','Membership Income','Other Income','Total']);

        foreach($incomeData as $row) fputcsv($output,[$row['month'],$row['membership_income'],$row['other_income'],$row['membership_income']+$row['other_income']]);

    }elseif($report==='attendance'){

        fputcsv($output,['Date','Total Check-ins']);

        foreach($attendanceData as $row) fputcsv($output,[$row['date'],$row['present']]);

    }

    fclose($output);

    exit;

}



if(isset($_GET['export']) && in_array($_GET['export'],$allowedReports,true)){

    exportCsv($_GET['export'],$membershipData,$incomeData,$attendanceData);

}



// === Report Logic ===

$reportTitle='';

$reportSubtitle='';

$summaryCards=[];

$tableHeaders=[];

$tableRows=[];

$hasReportData=false;



// --- Membership Report ---

if($report==='membership'){

    $membershipData=[];

    $plansResult=mysqli_query($conn,"SELECT plan_name,price FROM membership_plans WHERE is_active=1");

    while($plan=mysqli_fetch_assoc($plansResult)){

        $planName=mysqli_real_escape_string($conn,$plan['plan_name']);

        $planPrice=(float)$plan['price'];

        $membersResult=mysqli_query($conn,"

            SELECT COUNT(*) AS total_members

            FROM users

            WHERE (

                membership_plan='$planName'

                OR membership_plan=REPLACE('$planName', ' Membership', '')

            )

            AND status='Active'

            AND is_active=1

            AND role='member'

        ");

        $membersRow=mysqli_fetch_assoc($membersResult);

        $membershipData[]=[

            'plan'=>$planName,

            'members'=>(int)$membersRow['total_members'],

            'revenue'=>(int)$membersRow['total_members']*$planPrice

        ];

    }



    $totalMembers=array_sum(array_column($membershipData,'members'));

    $totalRevenue=array_sum(array_column($membershipData,'revenue'));

    $reportTitle='Membership Report';

    $reportSubtitle='Overview of active memberships and revenue by plan.';

    $summaryCards=[

        ['label'=>'Total Members','value'=>$totalMembers],

        ['label'=>'Plan Types','value'=>count($membershipData)],

        ['label'=>'Total Revenue','value'=>money($totalRevenue)]

    ];

    $tableHeaders=['Plan','Members','Revenue'];

    $tableRows=[];

    foreach($membershipData as $row) $tableRows[]=[$row['plan'],$row['members'],money($row['revenue'])];

}



// --- Income Report ---

elseif($report==='income'){

    $incomeData=[];

    $monthsResult=mysqli_query($conn,"SELECT DISTINCT DATE_FORMAT(payment_date,'%Y-%m') AS month FROM payments ORDER BY month ASC");

    while($monthRow=mysqli_fetch_assoc($monthsResult)){

        $month=$monthRow['month'];

        $membershipIncomeResult=mysqli_query($conn,"SELECT SUM(amount) AS total FROM payments WHERE DATE_FORMAT(payment_date,'%Y-%m')='$month'");

        $membershipRow=mysqli_fetch_assoc($membershipIncomeResult);

        $membershipTotal=(float)($membershipRow['total'] ?? 0);

        $otherIncome=0; // Extend if you track other income

        $incomeData[]=['month'=>$month,'membership_income'=>$membershipTotal,'other_income'=>$otherIncome];

    }

    $membershipIncomeTotal=array_sum(array_column($incomeData,'membership_income'));

    $otherIncomeTotal=array_sum(array_column($incomeData,'other_income'));

    $reportTitle='Income Report';

    $reportSubtitle='Monthly income from memberships and other services.';

    $summaryCards=[

        ['label'=>'Membership Income','value'=>money($membershipIncomeTotal)],

        ['label'=>'Total Income','value'=>money($membershipIncomeTotal+$otherIncomeTotal)]

    ];

    $tableHeaders=['Month','Membership Income','Other Income','Total'];

    $tableRows=[];

    foreach($incomeData as $row){

        $tableRows[]=[$row['month'],money($row['membership_income']),money($row['other_income']),money($row['membership_income']+$row['other_income'])];

    }

}



// --- Attendance Report ---

elseif($report==='attendance'){

    $attendanceData=[];

    $datesResult=mysqli_query($conn,"SELECT DATE(check_in_time) AS date, COUNT(*) AS total_checkins FROM attendance GROUP BY DATE(check_in_time) ORDER BY DATE(check_in_time) ASC");

    while($row=mysqli_fetch_assoc($datesResult)){

        $attendanceData[]=['date'=>$row['date'],'present'=>(int)$row['total_checkins']];

    }

    $totalCheckins=array_sum(array_column($attendanceData,'present'));

    $reportTitle='Attendance Report';

    $reportSubtitle='Daily attendance summary for members.';

    $summaryCards=[['label'=>'Total Check-ins','value'=>$totalCheckins]];

    $tableHeaders=['Date','Total Check-ins'];

    $tableRows=[];

    foreach($attendanceData as $row) $tableRows[]=[$row['date'],$row['present']];

}



$hasReportData=!empty($tableRows);



// --- Navigation ---

$navLinks=[

    'admin_dashboard.php'=>['Dashboard Overview',true,'fas fa-home'],

    'members.php'=>['Members',false,'fas fa-users'],

    'membership_plans.php'=>['Membership Plans',false,'fas fa-id-card'],

    'attendance.php'=>['Attendance',false,'fas fa-calendar-check'],

    'payments.php'=>['Payments',false,'fas fa-credit-card'],

    'trainers.php'=>['Trainers/Staffs',false,'fas fa-user-tie'],

    'equipment_list.php'=>['Equipment List',false,'fas fa-dumbbell'],

    'reports.php'=>['Reports',false,'fas fa-chart-bar'],

    '../index.php?logout=true'=>['Logout',false,'fas fa-sign-out-alt']

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reports</title>

   <link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>

        *{margin:0;padding:0;box-sizing:border-box}

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

            margin-bottom: 20px;

            padding: 10px;

            border-bottom: 2px solid #ca7bff;

        }



        .sidebar-title-container {

            padding: 20px 0;

            background: rgba(42, 26, 47, 0.88);

            text-align: center;

        }



        .sidebar {

            width: 250px;

            padding: 20px;

            border-radius: 10px;

            flex-shrink: 0;

            position: sticky;

            top: 20px;

            height: calc(100vh - 40px);

            background: linear-gradient(180deg, rgba(42, 26, 47, .9), rgba(34, 5, 61, .9));

            box-shadow: 0 4px 8px rgba(0, 0, 0, .4);

            backdrop-filter: blur(8px);

            -webkit-backdrop-filter: blur(8px);

        }



        .sidebar ul { list-style: none; }

        .sidebar li { margin: 15px 0; }

        .sidebar a {

            display: block;

            padding: 10px;

            border-radius: 5px;

            color: #fff;

            text-decoration: none;

            font-size: 16px;

            font-weight: 600;

            border-left: 5px solid transparent;

            transition: .25s;

        }



        .sidebar a:hover,

        .sidebar a.active {

            background: rgba(171, 82, 242, .32);

            border-left-color: #ca7bff;

            padding-left: 15px;

        }

        .main{

            flex:1;

            min-width:0;

            display:flex;

            flex-direction:column;

            gap:22px

        }

        h1{

            font-size:36px;

            font-weight:700;

            margin-bottom:8px;

            letter-spacing:.5px;

            text-shadow:0 2px 8px rgba(0,0,0,.35)

        }

        .glass{

            background:rgba(58,28,86,.30);

            border:1px solid rgba(202,123,255,.16);

            border-radius:12px;

            box-shadow:0 6px 18px rgba(0,0,0,.2);

            backdrop-filter:blur(14px);

            -webkit-backdrop-filter:blur(14px)

        }

        .report-grid{

            display:grid;

            grid-template-columns:repeat(3, minmax(240px, 1fr));

            gap:22px;

            align-items:stretch;

        }

        .report-card{

            padding:34px 24px 30px;

            min-height:308px;

            display:flex;

            flex-direction:column;

            align-items:center;

            justify-content:center;

            text-align:center;

            background:rgba(49,21,74,.44);

        }

        .report-icon{

            width:98px;

            height:98px;

            margin-bottom:26px;

            color:#d79cff;

            opacity:.95;

        }

        .report-icon svg{

            width:100%;

            height:100%;

            fill:none;

            stroke:currentColor;

            stroke-width:2.6;

            stroke-linecap:round;

            stroke-linejoin:round;

        }

        .report-card.membership .report-icon svg path,

        .report-card.membership .report-icon svg circle,

        .report-card.income .report-icon svg path{

            fill:currentColor;

            stroke:none;

        }

        .report-title{

            font-size:24px;

            font-weight:500;

            line-height:1.25;

            color:#f3e7ff;

            margin-bottom:28px;

        }

        .btn{

            border:none;

            cursor:pointer;

            color:#fff;

            border-radius:8px;

            font:600 14px 'Poppins',sans-serif;

            background:linear-gradient(180deg,#c45dff,#8d33f7);

            box-shadow:0 4px 10px rgba(124,44,244,.35);

            transition:.25s;

            text-decoration:none;

            display:inline-flex;

            align-items:center;

            justify-content:center;

            min-width:220px;

            padding:13px 24px;

            font-size:18px;

        }

        .btn:hover{opacity:.95;transform:translateY(-1px)}

        .btn.disabled{

            opacity:.45;

            pointer-events:none;

            box-shadow:none;

        }

        .details-card{

            padding:24px;

            background:rgba(54,25,80,.32);

        }

        .details-title{

            font-size:24px;

            font-weight:700;

            color:#f3e7ff;

            margin-bottom:8px;

        }

        .details-subtitle{

            color:#ead9ff;

            font-size:14px;

            margin-bottom:18px;

        }

        .summary-grid{

            display:grid;

            grid-template-columns:repeat(3, minmax(160px, 1fr));

            gap:16px;

            margin-bottom:20px;

        }

        .summary-box{

            padding:16px;

            border-radius:10px;

            background:rgba(40,15,61,.52);

            border:1px solid rgba(202,123,255,.16)

        }

        .summary-label{

            display:block;

            font-size:13px;

            color:#d9b8ff;

            margin-bottom:6px

        }

        .summary-value{

            font-size:24px;

            font-weight:700;

            color:#fff

        }

        .actions{

            display:flex;

            gap:12px;

            flex-wrap:wrap;

            margin-bottom:18px;

        }

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

        .empty-note{

            padding:18px 20px;

            color:#ead9ff;

            font-size:15px;

            background:rgba(40,15,61,.42);

            border:1px solid rgba(202,123,255,.16);

            border-radius:10px;

        }

        @media (max-width:1100px){

            .report-grid{grid-template-columns:1fr}

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

            .summary-grid{grid-template-columns:1fr}

            .btn{min-width:180px;font-size:16px}

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



        .glass {

            border-color: var(--vg-glass-border);

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

                    $currentFile = basename($_SERVER['PHP_SELF']);  // Get the current page name

                    foreach ($navLinks as $href => [$label, $active, $icon]):

                        $isActive = ($currentFile === basename(parse_url($href, PHP_URL_PATH))) ? 'active' : ''; // Check if the current page matches the href

                    ?>

                        <li>

                            <a href="<?= $href ?>" class="<?= $isActive ?>">

                                <i class="<?= $icon ?>"></i>

                                <span><?= $label ?></span>

                            </a>

                        </li>

                <?php endforeach; ?>

            </ul>

        </nav>



        <main class="main">

            <h1>Reports</h1>



            <section class="report-grid">

                <article class="glass report-card membership">

                    <div class="report-icon"><?php echo iconSvg('membership'); ?></div>

                    <h2 class="report-title">Membership Report</h2>

                    <a class="btn" href="reports.php?report=membership#report-details">View Report</a>

                </article>



                <article class="glass report-card income">

                    <div class="report-icon"><?php echo iconSvg('income'); ?></div>

                    <h2 class="report-title">Income Report</h2>

                    <a class="btn" href="reports.php?report=income#report-details">View Report</a>

                </article>



                <article class="glass report-card attendance">

                    <div class="report-icon"><?php echo iconSvg('attendance'); ?></div>

                    <h2 class="report-title">Attendance Report</h2>

                    <a class="btn" href="reports.php?report=attendance#report-details">View Report</a>

                </article>

            </section>



            <section id="report-details" class="glass details-card">

                <?php if ($report === ''): ?>

                    <div class="empty-note">Select any report above to view its full details.</div>

                <?php else: ?>

                    <h3 class="details-title"><?php echo h($reportTitle); ?></h3>

                    <p class="details-subtitle"><?php echo h($reportSubtitle); ?></p>



                    <div class="summary-grid">

                        <?php foreach ($summaryCards as $card): ?>

                            <div class="summary-box">

                                <span class="summary-label"><?php echo h($card['label']); ?></span>

                                <div class="summary-value"><?php echo h($card['value']); ?></div>

                            </div>

                        <?php endforeach; ?>

                    </div>



                    <div class="actions">

                        <a class="btn <?php echo $hasReportData ? '' : 'disabled'; ?>" href="<?php echo $hasReportData ? 'reports.php?report=' . h($report) . '&export=' . h($report) : '#'; ?>">Export CSV</a>

                        <button class="btn <?php echo $hasReportData ? '' : 'disabled'; ?>" type="button" <?php echo $hasReportData ? 'onclick="window\.print()"' : 'disabled'; ?>>Print Report</button>

                    </div>



                    <?php if (!$hasReportData): ?>

                        <div class="empty-note">No report data available yet for this category.</div>

                    <?php else: ?>

                        <div class="table-wrap">

                            <table>

                                <thead>

                                    <tr>

                                        <?php foreach ($tableHeaders as $header): ?>

                                            <th><?php echo h($header); ?></th>

                                        <?php endforeach; ?>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($tableRows as $row): ?>

                                        <tr>

                                            <?php foreach ($row as $cell): ?>

                                                <td><?php echo h($cell); ?></td>

                                            <?php endforeach; ?>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>

            </section>

        </main>

    </div>

</body>

</html>
