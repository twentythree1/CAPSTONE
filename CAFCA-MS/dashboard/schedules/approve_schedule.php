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

if (isset($_GET['id'])) {
    $scheduleId = intval($_GET['id']);
    
    // Get the schedule details including machine_id
    $getSql = "SELECT machine_id FROM schedules WHERE id = ?";
    $getStmt = $conn->prepare($getSql);
    $getStmt->bind_param("i", $scheduleId);
    $getStmt->execute();
    $result = $getStmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $machineId = $row['machine_id'];
        
        // Update schedule status to Approved
        $updateSql = "UPDATE schedules SET status = 'Approved' WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $scheduleId);
        
        if ($updateStmt->execute()) {
            // Update machine availability dates
            updateMachineAvailability($conn, $machineId);
            
            header("Location: schedule.php?status=Pending&approved=1");
        } else {
            header("Location: schedule.php?error=approval_failed");
        }
        
        $updateStmt->close();
    } else {
        header("Location: schedule.php?error=schedule_not_found");
    }
    
    $getStmt->close();
} else {
    header("Location: schedule.php");
}

$conn->close();
?>