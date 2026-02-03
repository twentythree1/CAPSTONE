<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the schedule ID from the URL
$schedule_id = $_GET['id'] ?? null;

if (!$schedule_id) {
    die("No schedule ID provided.");
}

// Update the schedule's status to "Cancelled"
$sql = "UPDATE schedules SET status = 'Cancelled' WHERE id = $schedule_id";
$result = $conn->query($sql);

if (!$result) {
    die("Error updating schedule status: " . $conn->error);
}

// Redirect back to the list of schedules
header("Location: schedule.php?status=Pending");
exit;
?>