<?php

session_start();

date_default_timezone_set('Asia/Manila');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);



$conn = mysqli_connect("localhost", "root", "", "vigor_gymm", "3306");

if (!$conn) {

    die("Database connection failed.");

}



mysqli_set_charset($conn, "utf8mb4");



function h($value)

{

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}



function clean($conn, $value)

{

    return mysqli_real_escape_string($conn, trim((string)$value));

}



function queryValue($conn, $sql, $key, $default = 0)

{

    $result = mysqli_query($conn, $sql);



    if (!$result) {

        return $default;

    }



    $row = mysqli_fetch_assoc($result);

    return $row[$key] ?? $default;

}



function columnExists($conn, $table, $column)

{

    $table = mysqli_real_escape_string($conn, $table);

    $column = mysqli_real_escape_string($conn, $column);

    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");

    return ($check && mysqli_num_rows($check) > 0);

}



function ensureWorkoutPresetsTable($conn)

{

   $createTable = "

    CREATE TABLE IF NOT EXISTS workout_presets (

        id INT AUTO_INCREMENT PRIMARY KEY,

        user_id INT NOT NULL,

        preset_name VARCHAR(150) NOT NULL,

        goal_focus VARCHAR(100) NOT NULL DEFAULT 'General Fitness',

        difficulty_level VARCHAR(50) NOT NULL DEFAULT 'Beginner',

        workout_days VARCHAR(100) DEFAULT '',

        duration_minutes INT NOT NULL DEFAULT 30,

        exercise_count INT NOT NULL DEFAULT 0,

        exercises TEXT NOT NULL,

        notes TEXT DEFAULT NULL,

        is_favorite TINYINT(1) NOT NULL DEFAULT 0,

        last_used_at DATETIME DEFAULT NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

";



    mysqli_query($conn, $createTable);



$columns = [

    'goal_focus' => "ALTER TABLE workout_presets ADD COLUMN goal_focus VARCHAR(100) NOT NULL DEFAULT 'General Fitness'",

    'difficulty_level' => "ALTER TABLE workout_presets ADD COLUMN difficulty_level VARCHAR(50) NOT NULL DEFAULT 'Beginner'",

    'workout_days' => "ALTER TABLE workout_presets ADD COLUMN workout_days VARCHAR(100) DEFAULT ''",

    'duration_minutes' => "ALTER TABLE workout_presets ADD COLUMN duration_minutes INT NOT NULL DEFAULT 30",

    'exercise_count' => "ALTER TABLE workout_presets ADD COLUMN exercise_count INT NOT NULL DEFAULT 0",

    'exercises' => "ALTER TABLE workout_presets ADD COLUMN exercises TEXT NOT NULL",

    'notes' => "ALTER TABLE workout_presets ADD COLUMN notes TEXT DEFAULT NULL",

    'is_favorite' => "ALTER TABLE workout_presets ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0",

    'last_used_at' => "ALTER TABLE workout_presets ADD COLUMN last_used_at DATETIME DEFAULT NULL",

    'created_at' => "ALTER TABLE workout_presets ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",

    'updated_at' => "ALTER TABLE workout_presets ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"

];

    foreach ($columns as $column => $query) {

        if (!columnExists($conn, 'workout_presets', $column)) {

            mysqli_query($conn, $query);

        }

    }

}



function parseExerciseLines($text)

{

    $lines = preg_split('/\r\n|\r|\n/', (string)$text);

    $items = [];



    foreach ($lines as $line) {

        $line = trim($line);

        $line = preg_replace('/^[•\-\*]\s*/u', '', $line);



        if ($line !== '') {

            $items[] = $line;

        }

    }



    return $items;

}



function countExercises($text)

{

    return count(parseExerciseLines($text));

}



function buildPresetUrl($extra = [])

{

    $params = $_GET;



    foreach ($extra as $key => $value) {

        if ($value === null) {

            unset($params[$key]);

        } else {

            $params[$key] = $value;

        }

    }



    return 'user_presets.php?' . http_build_query($params);

}



ensureWorkoutPresetsTable($conn);



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



$user_id = (int)$_SESSION['user_id'];



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



$user_name = $user['full_name'] ?? ($user['username'] ?? 'Member');

$membership_status = $user['status'] ?? 'Deactivated';

$is_active = (int)($user['is_active'] ?? 0);



if ($is_active !== 1 || $membership_status !== 'Active') {

    session_unset();

    session_destroy();

    header("Location: index.php");

    exit();

}



$msg = '';

$type = '';

$showModal = false;



if (isset($_GET['saved']) && $_GET['saved'] == '1') {

    $msg = 'Workout preset added successfully.';

    $type = 'success';

}



if (isset($_GET['updated']) && $_GET['updated'] == '1') {

    $msg = 'Workout preset updated successfully.';

    $type = 'success';

}



$presetId = 0;

$presetName = '';

$goalFocus = 'General Fitness';

$difficultyLevel = 'Beginner';

$workoutDays = '';

$durationMinutes = 30;

$exercises = '';

$notes = '';



if (isset($_POST['save_preset'])) {

    $presetId = (int)($_POST['preset_id'] ?? 0);

    $presetName = trim($_POST['preset_name'] ?? '');

    $goalFocus = trim($_POST['goal_focus'] ?? 'General Fitness');

    $difficultyLevel = trim($_POST['difficulty_level'] ?? 'Beginner');

    $workoutDays = trim($_POST['workout_days'] ?? '');

    $durationMinutes = (int)($_POST['duration_minutes'] ?? 30);

    $exercises = trim($_POST['exercises'] ?? '');

    $notes = trim($_POST['notes'] ?? '');

    $showModal = true;



    $exerciseCount = countExercises($exercises);



    if ($presetName === '' || $goalFocus === '' || $difficultyLevel === '' || $exercises === '') {

        $msg = 'Please complete all required preset fields.';

        $type = 'error';

    } elseif ($durationMinutes <= 0) {

        $msg = 'Please enter a valid workout duration.';

        $type = 'error';

    } elseif ($exerciseCount <= 0) {

        $msg = 'Please add at least one exercise.';

        $type = 'error';

    } else {

        $presetNameSafe = clean($conn, $presetName);

        $goalFocusSafe = clean($conn, $goalFocus);

        $difficultyLevelSafe = clean($conn, $difficultyLevel);

        $workoutDaysSafe = clean($conn, $workoutDays);

        $exercisesSafe = clean($conn, $exercises);

        $notesSafe = clean($conn, $notes);



        if ($presetId > 0) {

            $update = mysqli_query($conn, "

                UPDATE workout_presets

                SET preset_name = '$presetNameSafe',

                    goal_focus = '$goalFocusSafe',

                    difficulty_level = '$difficultyLevelSafe',

                    workout_days = '$workoutDaysSafe',

                    duration_minutes = $durationMinutes,

                    exercise_count = $exerciseCount,

                    exercises = '$exercisesSafe',

                    notes = '$notesSafe'

                WHERE id = $presetId AND user_id = $user_id

            ");



            if ($update) {

                header('Location: user_presets.php?updated=1');

                exit();

            } else {

                $msg = 'Unable to update workout preset: ' . mysqli_error($conn);

                $type = 'error';

            }

        } else {

            $insert = mysqli_query($conn, "

                INSERT INTO workout_presets (

                    user_id, preset_name, goal_focus, difficulty_level,

                    workout_days, duration_minutes, exercise_count, exercises, notes, is_favorite

                ) VALUES (

                    $user_id, '$presetNameSafe', '$goalFocusSafe', '$difficultyLevelSafe',

                    '$workoutDaysSafe', $durationMinutes, $exerciseCount, '$exercisesSafe', '$notesSafe', 0

                )

            ");



            if ($insert) {

                header('Location: user_presets.php?saved=1');

                exit();

            } else {

                $msg = 'Unable to add workout preset: ' . mysqli_error($conn);

                $type = 'error';

            }

        }

    }

}

if (isset($_POST['toggle_favorite'])) {

    $targetId = (int)($_POST['preset_id'] ?? 0);



    if ($targetId > 0) {

        $currentQuery = mysqli_query($conn, "

            SELECT is_favorite

            FROM workout_presets

            WHERE id = $targetId AND user_id = $user_id

            LIMIT 1

        ");



        if ($currentQuery && mysqli_num_rows($currentQuery) > 0) {

            $currentRow = mysqli_fetch_assoc($currentQuery);

            $newValue = ((int)$currentRow['is_favorite'] === 1) ? 0 : 1;



            mysqli_query($conn, "

                UPDATE workout_presets

                SET is_favorite = $newValue

                WHERE id = $targetId AND user_id = $user_id

            ");



            $msg = $newValue ? 'Preset added to favorites.' : 'Preset removed from favorites.';

            $type = 'success';

        }

    }

}



if (isset($_POST['use_preset'])) {

    $targetId = (int)($_POST['preset_id'] ?? 0);



    if ($targetId > 0) {

        mysqli_query($conn, "

            UPDATE workout_presets

            SET last_used_at = NOW()

            WHERE id = $targetId AND user_id = $user_id

        ");



        $msg = 'Preset marked as used.';

        $type = 'success';

    }

}



if (isset($_POST['delete_preset'])) {

    $targetId = (int)($_POST['preset_id'] ?? 0);



    if ($targetId > 0) {

        mysqli_query($conn, "

            DELETE FROM workout_presets

            WHERE id = $targetId AND user_id = $user_id

        ");



        $msg = 'Preset deleted successfully.';

        $type = 'success';

    }

}



if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];



    if ($editId > 0) {

        $editQuery = mysqli_query($conn, "

            SELECT *

            FROM workout_presets

            WHERE id = $editId AND user_id = $user_id

            LIMIT 1

        ");



        if ($editQuery && mysqli_num_rows($editQuery) > 0) {

            $editPreset = mysqli_fetch_assoc($editQuery);



            $presetId = (int)($editPreset['id'] ?? 0);

            $presetName = $editPreset['preset_name'] ?? '';

            $goalFocus = $editPreset['goal_focus'] ?? 'General Fitness';

            $difficultyLevel = $editPreset['difficulty_level'] ?? 'Beginner';

            $workoutDays = $editPreset['workout_days'] ?? '';

            $durationMinutes = (int)($editPreset['duration_minutes'] ?? 30);

            $exercises = $editPreset['exercises'] ?? '';

            $notes = $editPreset['notes'] ?? '';

            $showModal = true;

        }

    }

}



$goalFilter = trim($_GET['goal'] ?? 'All Goals');

$difficultyFilter = trim($_GET['difficulty'] ?? 'All Levels');

$search = trim($_GET['search'] ?? '');



$whereParts = ["user_id = $user_id"];



if ($goalFilter !== 'All Goals') {

    $goalFilterSafe = clean($conn, $goalFilter);

    $whereParts[] = "goal_focus = '$goalFilterSafe'";

}



if ($difficultyFilter !== 'All Levels') {

    $difficultyFilterSafe = clean($conn, $difficultyFilter);

    $whereParts[] = "difficulty_level = '$difficultyFilterSafe'";

}



if ($search !== '') {

    $searchSafe = clean($conn, $search);

    $whereParts[] = "(

        preset_name LIKE '%$searchSafe%' OR

        goal_focus LIKE '%$searchSafe%' OR

        difficulty_level LIKE '%$searchSafe%' OR

        exercises LIKE '%$searchSafe%'

    )";

}



$whereSql = "WHERE " . implode(' AND ', $whereParts);



$presetRows = [];

$presetQuery = mysqli_query($conn, "

    SELECT *

    FROM workout_presets

    $whereSql

    ORDER BY is_favorite DESC, last_used_at DESC, created_at DESC, id DESC

");



if ($presetQuery) {

    while ($row = mysqli_fetch_assoc($presetQuery)) {

        $presetRows[] = $row;

    }

}



$viewId = (int)($_GET['view'] ?? 0);

$selectedPreset = null;



if ($viewId > 0) {

    foreach ($presetRows as $row) {

        if ((int)$row['id'] === $viewId) {

            $selectedPreset = $row;

            break;

        }

    }

}



if ($selectedPreset === null && !empty($presetRows)) {

    $selectedPreset = $presetRows[0];

}



$totalPresets = (int)queryValue(

    $conn,

    "SELECT COUNT(*) AS total FROM workout_presets WHERE user_id = $user_id",

    'total',

    0

);



$favoritePresetName = queryValue(

    $conn,

    "SELECT preset_name AS favorite_name

     FROM workout_presets

     WHERE user_id = $user_id AND is_favorite = 1

     ORDER BY last_used_at DESC, created_at DESC, id DESC

     LIMIT 1",

    'favorite_name',

    'None yet'

);



$lastUsedPresetName = queryValue(

    $conn,

    "SELECT preset_name AS last_used_name

     FROM workout_presets

     WHERE user_id = $user_id AND last_used_at IS NOT NULL

     ORDER BY last_used_at DESC

     LIMIT 1",

    'last_used_name',

    'None yet'

);



$recommendedPresetName = !empty($favoritePresetName) && $favoritePresetName !== 'None yet'

    ? $favoritePresetName

    : (!empty($presetRows) ? ($presetRows[0]['preset_name'] ?? 'No preset available') : 'No preset available');



$goalOptions = ['All Goals', 'General Fitness', 'Weight Loss', 'Muscle Gain', 'Strength', 'Home Workout', 'Fat Loss', 'Beginner'];

$difficultyOptions = ['All Levels', 'Beginner', 'Intermediate', 'Advanced'];



$navLinks = [

    'user_dashboard.php' => ['Dashboard Overview', false, 'fas fa-home'],

    'user_attendance.php' => ['Attendance', false, 'fas fa-calendar-check'],

    'user_membership.php' => ['Membership', false, 'fas fa-id-card'],

    'user_presets.php' => ['Workout Presets', true, 'fas fa-dumbbell'],

    '../logout.php?logout=true' => ['Logout', false, 'fas fa-sign-out-alt'],

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Workout Presets</title>



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

            width: 260px;

            min-width: 260px;

            flex: 0 0 260px;

            height: calc(100vh - 40px);

            min-height: 600px;

            background: linear-gradient(180deg, rgba(42, 26, 47, 0.88) 0%, rgba(34, 5, 61, 0.88) 100%);

            padding: 20px;

            border-radius: 12px;

            position: sticky;

            top: 20px;

            overflow-y: auto;

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



        .summary-grid {

            display: flex;

            gap: 15px;

            flex-wrap: wrap;

        }



        .summary-card,

        .filter-card,

        .preset-card,

        .detail-card {

            background: rgba(92, 61, 176, 0.18);

            backdrop-filter: blur(10px);

            -webkit-backdrop-filter: blur(10px);

            border: 1px solid rgba(202, 123, 255, 0.25);

            border-radius: 14px;

            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);

        }



        .summary-card {

            padding: 20px;

            width: calc(25% - 12px);

            text-align: center;

        }



        .summary-card h3,

        .detail-card h3,

        .filter-card h3 {

            color: #e9c8ff;

            margin-bottom: 10px;

            font-size: 16px;

            font-weight: 600;

        }



        .summary-card p {

            font-size: 24px;

            font-weight: 700;

            color: #fff;

            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);

        }



        .filter-card {

            padding:20px;

        }



        .filter-top {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 14px;

            flex-wrap: wrap;

            margin-bottom: 16px;

        }



        .add-btn,

        .mini-btn,

        .danger-btn {

            border: none;

            cursor: pointer;

            border-radius: 8px;

            color: #fff;

            font-family: 'Poppins', sans-serif;

            font-weight: 600;

            text-decoration: none;

            display: inline-flex;

            align-items: center;

            justify-content: center;

        }



        .add-btn,

        .mini-btn {

            background: linear-gradient(180deg, #a245ff, #7c2cf4);

            box-shadow: 0 4px 10px rgba(124, 44, 244, 0.35);

        }



        .add-btn {

            padding: 12px 18px;

            gap: 8px;

        }



        .mini-btn,

        .danger-btn {

            padding: 10px 14px;

            font-size: 13px;

            min-width: 110px;

        }



        .danger-btn {

            background: linear-gradient(180deg, #c45dff, #8d33f7);

        }



        .filter-form {

            display: flex;

            gap: 12px;

            flex-wrap: wrap;

            align-items: center;

        }



        .filter-form input,

        .filter-form select,

        .modal-card input,

        .modal-card select,

        .modal-card textarea {

            padding: 12px 14px;

            border-radius: 8px;

            border: 1px solid rgba(202, 123, 255, 0.25);

            background: rgba(30, 12, 53, 0.78);

            color: #fff;

            font-family: 'Poppins', sans-serif;

            outline: none;

        }



        .filter-form input,

        .filter-form select {

            min-width: 200px;

        }



        .filter-form select option,

        .modal-card select option {

            color: #000;

        }



        .content-grid {

            display: block;

            grid-template-columns: 1.1fr 0.9fr;

            gap: 20px;

        }



        .presets-list {

            display: flex;

            flex-direction: ;

            gap: 16px;

        }



        .preset-library-card {

            width: 100%;

            display: flex;

            justify-content: center;

            margin-bottom: 16px; /* spacing between cards */

        }



        .preset-card {

            width: 100%; /* take full width of the library container */

            max-width: 100%; /* prevent shrinking */

            padding: 18px;

            border-radius: 12px;

            background: rgba(92, 61, 176, 0.18);

            box-shadow: 0 6px 18px rgba(0,0,0,0.2);

        }



        .preset-head {

            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            gap: 30px;

            flex-wrap: wrap;

            margin-bottom: 14px;

        }



        .preset-title {

            font-size: 30px;

            font-weight: 700;

            color: #fff;

        }



        .badge-row {

            display: flex;

            gap: 12px;

            flex-wrap: wrap;

            margin-top: 12px;

        }



        .badge {

            display: inline-block;

            padding: 6px 12px;

            border-radius: 999px;

            font-size: 16px;

            font-weight: 700;

            background: rgba(124, 77, 255, 0.25);

            border: 1px solid rgba(202, 123, 255, 0.25);

        }



        .favorite-badge {

            background: rgba(250, 204, 21, 0.18);

            border: 1px solid rgba(250, 204, 21, 0.35);

        }



       .preset-meta {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 10px 14px;

            margin-bottom: 14px;

        }



        .meta-item {

            font-size: 20px;

            color: #f3e7ff;

        }



        .meta-item strong {

            color: #e9c8ff;

        }



        .exercise-preview {

            margin-top: 12px;

            padding-left: 0px;

        }



        .exercise-preview li {

            margin-bottom: 6px;

            color: #fff;

        }



        .card-actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 16px;

        }



        .action-form {

            display: inline;

        }



        .detail-card {

            padding: 22px;

            height: fit-content;

        }



        .detail-card p,

        .detail-card li {

            color: #fff;

            line-height: 1.7;

        }



        .detail-row {

            margin-bottom: 12px;

        }



        .detail-label {

            color: #e9c8ff;

            font-weight: 600;

        }



        .detail-exercises {

            padding-left: 22px;

            margin-top: 8px;

        }



        .detail-exercises li {

            margin-bottom: 8px;

        }



        .empty-box {

            padding: 24px;

            text-align: center;

            color: #eadcff;

        }



        .msg {

            padding: 14px 18px;

            font-size: 15px;

            border-radius: 10px;

            border: 1px solid rgba(255, 255, 255, 0.05);

            background: rgba(255, 255, 255, 0.03);

        }



        .msg.success {

            color: #d8ffe5;

            box-shadow: 0 0 10px rgba(80, 220, 140, 0.08);

        }



        .msg.error {

            color: #ffb0d2;

            box-shadow: 0 0 10px rgba(255, 120, 180, 0.06);

        }



        .modal {

            position: fixed;

            inset: 0;

            display: <?= $showModal ? 'flex' : 'none' ?>;

            align-items: center;

            justify-content: center;

            padding: 20px;

            background: rgba(0, 0, 0, 0.7);

            z-index: 999;

        }



        .modal-card {

            width: 100%;

            max-width: 760px;

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



        .modal-card textarea {

            min-height: 140px;

            resize: vertical;

        }



        .modal-actions {

            display: flex;

            justify-content: flex-end;

            gap: 12px;

            margin-top: 18px;

            flex-wrap: wrap;

        }



        @media (max-width: 1100px) {

            .summary-card {

                width: calc(50% - 8px);

            }



            .content-grid {

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



            .summary-card {

                width: 100%;

            }



            .form-grid {

                grid-template-columns: 1fr;

            }



            .preset-meta {

                grid-template-columns: 1fr;

            }



            .filter-form input,

            .filter-form select {

                min-width: 100%;

                width: 100%;

            }

        }



        /* Workout preset card refinement (this page uses cards, not a table) */

        .summary-card, .filter-card, .preset-card, .detail-card { border: 1px solid rgba(202, 123, 255, .30); box-shadow: 0 8px 22px rgba(0, 0, 0, .25); }

        .summary-card { border-radius: 15px; transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }

        .summary-card:hover { transform: translateY(-3px); border-color: rgba(226, 165, 255, .65); box-shadow: 0 10px 25px rgba(124, 44, 244, .18); }

        .preset-card { padding: 22px; border-radius: 15px; transition: border-color .2s ease, box-shadow .2s ease; }

        .preset-card:hover { border-color: rgba(226, 165, 255, .65); box-shadow: 0 10px 25px rgba(124, 44, 244, .18); }

        .preset-title { font-size: 25px; line-height: 1.3; }

        .badge { padding: 6px 11px; font-size: 13px; }

        .meta-item { font-size: 15px; line-height: 1.6; }

        .filter-card { border-radius: 15px; }





        /* VIGOR GYM - CONSISTENT USER INTERFACE */

        .sidebar-title-container {

            padding: 20px 12px 18px;

            margin-bottom: 18px;

            background: linear-gradient(135deg, rgba(85, 35, 125, 0.78), rgba(35, 10, 55, 0.96));

            text-align: center;

            border: 1px solid rgba(202, 123, 255, 0.25);

            border-radius: 12px;

        }

        .sidebar-brand-name { color: #fff; font-size: 23px; font-weight: 800; letter-spacing: 2px; line-height: 1.2; }

        .sidebar-brand-name span { color: #d9a5ff; }

        .sidebar-brand-subtitle { margin-top: 6px; color: #eadcff; font-size: 10px; font-weight: 700; letter-spacing: 3px; }

        .sidebar-profile { display: flex; align-items: center; gap: 12px; padding: 12px; margin-bottom: 20px; background: rgba(171, 82, 242, 0.13); border: 1px solid rgba(202, 123, 255, 0.25); border-radius: 12px; }

        .profile-icon { width: 42px; height: 42px; min-width: 42px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: linear-gradient(135deg, #d18aff, #8734c5); color: #fff; font-size: 18px; }

        .profile-info { display: flex; flex-direction: column; min-width: 0; }

        .profile-info strong { color: #fff; font-size: 13px; font-weight: 700; overflow-wrap: anywhere; }

        .profile-info span { color: #d9baff; font-size: 11px; margin-top: 3px; }

        nav.sidebar ul li { margin: 10px 0; }

        nav.sidebar ul li a { display: flex; align-items: center; gap: 13px; min-height: 48px; padding: 12px 13px; border-radius: 9px; border-left: 5px solid transparent; color: #fff; font-size: 18px; font-weight: 600; text-decoration: none; transition: background .25s ease, color .25s ease, padding .25s ease, box-shadow .25s ease; }

        nav.sidebar ul li a i { width: 22px; min-width: 22px; text-align: center; color: #d9a5ff; font-size: 16px; }

        nav.sidebar ul li a span { line-height: 1.4; }

        nav.sidebar ul li a:hover { background: rgba(171, 82, 242, .22); border-left-color: #ca7bff; padding-left: 18px; color: #f0caff; box-shadow: 0 3px 12px rgba(171, 82, 242, .12); }

        nav.sidebar ul li a.active { background: linear-gradient(90deg, rgba(171, 82, 242, .45), rgba(115, 45, 160, .20)); border-left-color: #e2a5ff; color: #fff; font-weight: 700; box-shadow: 0 0 12px rgba(190, 100, 255, .18); }

        .page-heading { position: relative; padding: 4px 0 16px; margin-bottom: 2px; }

        .page-title { position: relative; display: inline-block; margin: 0 0 8px; color: #fff; font-size: 36px; font-weight: 800; line-height: 1.2; letter-spacing: -.5px; text-shadow: 0 2px 8px rgba(0, 0, 0, .35); }

        .page-title::after { content: ""; display: block; width: 76px; height: 4px; margin-top: 10px; border-radius: 99px; background: linear-gradient(90deg, #e2a5ff, #8734c5); }

        .welcome-line { color: #eadcff; font-size: 14px; margin-top: 2px; }

        @media (max-width: 768px) { nav.sidebar { width: 100%; height: auto; position: relative; top: 0; } .page-title { font-size: 29px; } }



    /* CONSISTENT USER SIDEBAR RESPONSIVE SIZE */

@media (max-width: 768px) {

    nav.sidebar {

        width: 100%;

        min-width: 0;

        flex: 0 0 auto;

        height: auto;

        min-height: 0;

        position: relative;

        top: 0;

    }

}



/* MATCH THE ADMIN REPORTS LAYOUT */

.admin-dashboard {

    width: 100%;

    min-height: 100vh;

    display: flex;

    flex-direction: row;

    align-items: flex-start;

    gap: 16px;

    padding: 16px;

    box-sizing: border-box;

}



/* MATCH THE ADMIN SIDEBAR SIZE */

.admin-dashboard nav.sidebar {

    width: 260px;

    min-width: 260px;

    flex: 0 0 260px;



    height: calc(100vh - 40px);

    min-height: 600px;



    position: sticky;

    top: 20px;



    padding: 20px;

    overflow-y: auto;

    box-sizing: border-box;

}



/* RESPONSIVE DESIGN */

@media (max-width: 768px) {

    .admin-dashboard {

        flex-direction: column;

        gap: 14px;

        padding: 12px;

    }



    .admin-dashboard nav.sidebar {

        position: relative;

        top: 0;

        width: 100%;

        min-width: 0;

        flex: 0 0 auto;

        height: auto;

        min-height: 0;

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

            <h1 class="page-title">Workout Presets</h1>

            <div class="welcome-line">Welcome back, <?= h($user_name) ?>!</div>

        </div>



        <?php if ($msg !== ''): ?>

            <div class="msg <?= h($type) ?>"><?= h($msg) ?></div>

        <?php endif; ?>



        <div class="summary-grid">

            <div class="summary-card">

                <h3>Total Presets</h3>

                <p><?= (int)$totalPresets ?></p>

            </div>



            <div class="summary-card">

                <h3>Favorite Preset</h3>

                <p><?= h($favoritePresetName) ?></p>

            </div>



            <div class="summary-card">

                <h3>Last Used Preset</h3>

                <p><?= h($lastUsedPresetName) ?></p>

            </div>



            <div class="summary-card">

                <h3>Recommended Preset</h3>

                <p><?= h($recommendedPresetName) ?></p>

            </div>

        </div>



        <div class="filter-card">

            <div class="filter-top">

                <h3>Your Preset Library</h3>

                <button type="button" class="add-btn" id="openPresetModal">

                    <i class="fas fa-plus"></i> Add Preset

                </button>

            </div>



            <form method="get" class="filter-form">

                <select name="goal" onchange="this.form.submit()">

                    <?php foreach ($goalOptions as $option): ?>

                        <option value="<?= h($option) ?>" <?= $goalFilter === $option ? 'selected' : '' ?>>

                            <?= h($option) ?>

                        </option>

                    <?php endforeach; ?>

                </select>



                <select name="difficulty" onchange="this.form.submit()">

                    <?php foreach ($difficultyOptions as $option): ?>

                        <option value="<?= h($option) ?>" <?= $difficultyFilter === $option ? 'selected' : '' ?>>

                            <?= h($option) ?>

                        </option>

                    <?php endforeach; ?>

                </select>



                <input

                    type="text"

                    name="search"

                    value="<?= h($search) ?>"

                    placeholder="Search preset, goal, or exercise..."

                >

            </form>

        </div>



        <div class="content-grid">

            <div class="presets-list">

                <?php if (empty($presetRows)): ?>

                    <div class="preset-card">

                        <div class="empty-box">No workout presets found. Add your first preset to get started.</div>

                    </div>

                <?php else: ?>

                    <?php foreach ($presetRows as $row): ?>

                        <?php

                            $exerciseItems = parseExerciseLines($row['exercises'] ?? '');

                            $previewItems = array_slice($exerciseItems, 0, 3);

                        ?>

                        <div class="preset-card">

                            <div class="preset-head">

                                <div class="preset-title"><?= h($row['preset_name'] ?? '') ?></div>

                                <div class="badge-row">

                                    <span class="badge"><?= h($row['goal_focus'] ?? 'General Fitness') ?></span>

                                    <span class="badge"><?= h($row['difficulty_level'] ?? 'Beginner') ?></span>

                                    <?php if ((int)($row['is_favorite'] ?? 0) === 1): ?>

                                        <span class="badge favorite-badge">Favorite</span>

                                    <?php endif; ?>

                                </div>

                            </div>



                            <div class="preset-meta">

                                <div class="meta-item"><strong>Workout Days:</strong> <?= h($row['workout_days'] ?: 'Flexible') ?></div>

                                <div class="meta-item"><strong>Duration:</strong> <?= (int)($row['duration_minutes'] ?? 0) ?> mins</div>

                                <div class="meta-item"><strong>Exercises:</strong> <?= (int)($row['exercise_count'] ?? 0) ?></div>

                                <div class="meta-item"><strong>Last Used:</strong> <?= !empty($row['last_used_at']) ? h(date('M j, Y g:i A', strtotime($row['last_used_at']))) : 'Not yet used' ?></div>

                            </div>



                            <?php if (!empty($previewItems)): ?>

                                <ul class="exercise-preview">

                                    <?php foreach ($previewItems as $item): ?>

                                        <li><?= h($item) ?></li>

                                    <?php endforeach; ?>

                                </ul>

                            <?php endif; ?>



                            <div class="card-actions">

                                <a class="mini-btn" href="<?= h(buildPresetUrl(['view' => (int)$row['id']])) ?>">View Preset</a>

                                <form method="post" class="action-form">

                                    <input type="hidden" name="preset_id" value="<?= (int)$row['id'] ?>">

                                    <button type="submit" name="use_preset" class="mini-btn">Use Preset</button>

                                </form>

                                <a class="mini-btn" href="user_presets.php?edit=<?= (int)$row['id'] ?>">Edit</a>

                                <form method="post" class="action-form">

                                    <input type="hidden" name="preset_id" value="<?= (int)$row['id'] ?>">

                                    <button type="submit" name="toggle_favorite" class="mini-btn">

                                        <?= (int)($row['is_favorite'] ?? 0) === 1 ? 'Unfavorite' : 'Favorite' ?>

                                    </button>

                                </form>

                                <form method="post" class="action-form" onsubmit="return confirm('Delete this preset?');">

                                    <input type="hidden" name="preset_id" value="<?= (int)$row['id'] ?>">

                                    <button type="submit" name="delete_preset" class="danger-btn">Delete</button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>



        </div>

    </div>

</div>



<div class="modal" id="presetModal">

    <div class="modal-card">

        <div class="modal-header">

            <h2><?= $presetId > 0 ? 'Edit Workout Preset' : 'Add Workout Preset' ?></h2>

            <button type="button" class="close-btn" id="closePresetModal">×</button>

        </div>



        <form method="post">

            <input type="hidden" name="preset_id" value="<?= (int)$presetId ?>">



            <div class="form-grid">

                <div class="form-group full">

                    <label for="preset_name">Preset Name</label>

                    <input type="text" id="preset_name" name="preset_name" value="<?= h($presetName) ?>" required>

                </div>



                <div class="form-group">

                    <label for="goal_focus">Goal Focus</label>

                    <select id="goal_focus" name="goal_focus" required>

                        <?php foreach (array_slice($goalOptions, 1) as $goalOption): ?>

                            <option value="<?= h($goalOption) ?>" <?= $goalFocus === $goalOption ? 'selected' : '' ?>>

                                <?= h($goalOption) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div class="form-group">

                    <label for="difficulty_level">Difficulty Level</label>

                    <select id="difficulty_level" name="difficulty_level" required>

                        <?php foreach (array_slice($difficultyOptions, 1) as $difficultyOption): ?>

                            <option value="<?= h($difficultyOption) ?>" <?= $difficultyLevel === $difficultyOption ? 'selected' : '' ?>>

                                <?= h($difficultyOption) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div class="form-group">

                    <label for="workout_days">Workout Days</label>

                    <input type="text" id="workout_days" name="workout_days" value="<?= h($workoutDays) ?>" placeholder="e.g. Mon-Wed-Fri">

                </div>



                <div class="form-group">

                    <label for="duration_minutes">Duration (Minutes)</label>

                    <input type="number" id="duration_minutes" name="duration_minutes" value="<?= (int)$durationMinutes ?>" min="1" required>

                </div>



                <div class="form-group full">

                    <label for="exercises">Exercises</label>

                    <textarea id="exercises" name="exercises" placeholder="One exercise per line. Example:

Push-ups - 3 sets x 15 reps

Squats - 4 sets x 12 reps

Plank - 3 sets x 30 seconds" required><?= h($exercises) ?></textarea>

                </div>



                <div class="form-group full">

                    <label for="notes">Notes</label>

                    <textarea id="notes" name="notes" placeholder="Optional reminders, rest times, or coaching notes"><?= h($notes) ?></textarea>

                </div>

            </div>



            <div class="modal-actions">

                <button type="button" class="mini-btn" id="cancelPresetModal">Cancel</button>

                <button type="submit" name="save_preset" class="mini-btn">

                    <?= $presetId > 0 ? 'Save Changes' : 'Save Preset' ?>

                </button>

            </div>

        </form>

    </div>

</div>



<script>

document.addEventListener('DOMContentLoaded', function () {

    const presetModal = document.getElementById('presetModal');

    const openPresetModal = document.getElementById('openPresetModal');

    const closePresetModal = document.getElementById('closePresetModal');

    const cancelPresetModal = document.getElementById('cancelPresetModal');



    if (openPresetModal) {

        openPresetModal.addEventListener('click', function () {

            presetModal.style.display = 'flex';

        });

    }



    if (closePresetModal) {

        closePresetModal.addEventListener('click', function () {

            presetModal.style.display = 'none';

            if (window.location.search.includes('edit=') || window.location.search.includes('new=1')) {

                window.location.href = 'user_presets.php';

            }

        });

    }



    if (cancelPresetModal) {

        cancelPresetModal.addEventListener('click', function () {

            presetModal.style.display = 'none';

            if (window.location.search.includes('edit=') || window.location.search.includes('new=1')) {

                window.location.href = 'user_presets.php';

            }

        });

    }



    window.addEventListener('click', function (event) {

        if (event.target === presetModal) {

            presetModal.style.display = 'none';

            if (window.location.search.includes('edit=') || window.location.search.includes('new=1')) {

                window.location.href = 'user_presets.php';

            }

        }

    });

});

</script>



</body>

</html>