<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: /CAPSTONE/CAFCA-MS/login/logindex.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include the machine availability helper function
require_once('../machines/update_machine_availability.php');

$schedule_id = $_GET['id'] ?? null;
$redirect = $_GET['redirect'] ?? 'Pending';

if (!$schedule_id) {
    die("No schedule ID provided.");
}

// Get the machine_id and current status before cancelling
$getSql = "SELECT machine_id, status FROM schedules WHERE id = ?";
$getStmt = $conn->prepare($getSql);
$getStmt->bind_param("i", $schedule_id);
$getStmt->execute();
$result = $getStmt->get_result();

if ($row = $result->fetch_assoc()) {
    $machineId = $row['machine_id'];
    $currentStatus = $row['status'];
    
    // Update the schedule status to 'Cancelled'
    $sql = "UPDATE schedules SET status = 'Cancelled' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $schedule_id);
    $result = $stmt->execute();
    
    if (!$result) {
        die("Error updating schedule status: " . $conn->error);
    }
    
    $stmt->close();
    
    // Only update machine availability if the schedule was Approved or On going
    if ($currentStatus === 'Approved' || $currentStatus === 'On going') {
        updateMachineAvailability($conn, $machineId);
    }
    
    $getStmt->close();
    $conn->close();
    
    // Redirect back to the list of schedules
    header("Location: schedule.php?status=" . urlencode($redirect) . "&cancelled=1");
    exit;
} else {
    $getStmt->close();
    $conn->close();
    die("Schedule not found.");
}
?>