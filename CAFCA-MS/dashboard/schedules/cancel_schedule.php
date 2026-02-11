<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$schedule_id = $_GET['id'] ?? null;

if (!$schedule_id) {
    die("No schedule ID provided.");
}

// Update the schedule status to 'Cancelled'
$sql = "UPDATE schedules SET status = 'Cancelled' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$result = $stmt->execute();

if (!$result) {
    die("Error updating schedule status: " . $conn->error);
}

$stmt->close();
$conn->close();

// Redirect back to the list of schedules
header("Location: schedule.php?status=Pending");
exit;
?>