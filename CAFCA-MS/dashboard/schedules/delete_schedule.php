<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: /CAFCA-MS/login/logindex.php");
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

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Verify the schedule exists and is expired
    $checkSql = "SELECT status FROM schedules WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['status'] === 'Expired') {
            $deleteSql = "DELETE FROM schedules WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            $deleteStmt->bind_param("i", $id);
            
            if ($deleteStmt->execute()) {
                header("Location: schedule.php?status=Expired&deleted=1");
            } else {
                header("Location: schedule.php?status=Expired&error=delete_failed");
            }
            $deleteStmt->close();
        } else {
            header("Location: schedule.php?error=not_expired");
        }
    } else {
        header("Location: schedule.php?error=not_found");
    }
    
    $checkStmt->close();
} else {
    header("Location: schedule.php?error=invalid_id");
}

$conn->close();
?>