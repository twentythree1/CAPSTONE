<?php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Include the machine availability helper function
require_once('update_machine_availability.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
$machine_id = isset($_POST['machine_id']) ? intval($_POST['machine_id']) : 0;
$machine_status = $_POST['machine_status'] ?? '';
$return_notes = $_POST['return_notes'] ?? '';

// Validate inputs
if ($schedule_id <= 0 || $machine_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid schedule or machine ID']);
    exit();
}

if (empty($machine_status)) {
    echo json_encode(['success' => false, 'message' => 'Machine status is required']);
    exit();
}

// Validate machine status
$validStatuses = ['Available', 'Partially Damaged', 'Totally Damaged'];
if (!in_array($machine_status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid machine status']);
    exit();
}

// Check if schedule exists
$check_sql = "SELECT id, machine_id, status FROM schedules WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $schedule_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    exit();
}

$schedule = $check_result->fetch_assoc();
$check_stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Update the schedule status to 'Completed'
    $update_schedule_sql = "UPDATE schedules 
                           SET status = 'Completed',
                               return_date = NOW(),
                               return_notes = ?
                           WHERE id = ?";
    
    $update_schedule_stmt = $conn->prepare($update_schedule_sql);
    $update_schedule_stmt->bind_param("si", $return_notes, $schedule_id);
    
    if (!$update_schedule_stmt->execute()) {
        throw new Exception('Failed to update schedule status');
    }
    $update_schedule_stmt->close();
    
    // Update the machine status
    $update_machine_sql = "UPDATE machines 
                          SET status = ?,
                              last_returned = NOW()
                          WHERE id = ?";
    
    $update_machine_stmt = $conn->prepare($update_machine_sql);
    $update_machine_stmt->bind_param("si", $machine_status, $machine_id);
    
    if (!$update_machine_stmt->execute()) {
        throw new Exception('Failed to update machine status');
    }
    $update_machine_stmt->close();
    
    // Record in machine history if there are notes or status changed
    if (!empty($return_notes) || $machine_status !== 'Available') {
        $history_sql = "INSERT INTO machine_history (machine_id, schedule_id, status_before, status_after, notes, changed_by, changed_at)
                       VALUES (?, ?, 'In Use', ?, ?, ?, NOW())";
        
        $history_stmt = $conn->prepare($history_sql);
        $changed_by = $_SESSION['username'];
        $history_stmt->bind_param("iisss", $machine_id, $schedule_id, $machine_status, $return_notes, $changed_by);
        $history_stmt->execute();
        $history_stmt->close();
    }
    updateMachineAvailability($conn, $machine_id);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Machine returned successfully',
        'machine_status' => $machine_status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to process return: ' . $e->getMessage()]);
}

$conn->close();

?>