<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$rawRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : null;
$rawRedirect = is_string($rawRedirect) ? trim($rawRedirect) : '';

if ($rawRedirect === '') {
    $backUrl = 'schedule.php';
} else {
    $san = filter_var($rawRedirect, FILTER_SANITIZE_STRING);

    if (strpos($san, 'status=') !== false || stripos($san, 'schedule.php') !== false || preg_match('#^/|https?://#i', $san)) {
        $backUrl = $san;
    } else {
        $backUrl = 'schedule.php?status=' . urlencode($san);
    }
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT s.*, f.name AS farmer_name, m.name AS machine_name 
            FROM schedules s
            JOIN farmers f ON s.farmer_id = f.id
            JOIN machines m ON s.machine_id = m.id
            WHERE s.id = $id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
    } else {
        die("Schedule not found.");
    }
} else {
    die("Invalid request.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../assets/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="details.css">
    <title>Schedule Certificate</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp">
</head>
<body>

<div class="certificate">
    <img src="../../assets/logo.png">
    <h2>Schedule Certificate</h2>
    <p>This is to certify that:</p>
    <h3><?= htmlspecialchars($row['farmer_name']); ?></h3>
    <p>is scheduled to use the machine:</p>
    <h3><?= htmlspecialchars($row['machine_name']); ?></h3>
    <p>on <strong><?= htmlspecialchars($row['schedule_date']); ?></strong></p>
    <p>from <strong><?= htmlspecialchars($row['start_time']); ?></strong> to <strong><?= htmlspecialchars($row['end_time']); ?></strong></p>
</div>

<div class="buttons">
<a class="btn-print" onclick="window.print();">Print <span class="material-icons-sharp">print</span></a>
<a href="<?= htmlspecialchars($backUrl) ?>" class="btn-danger" role="button">Back</a>
</div>


</body>
</html>