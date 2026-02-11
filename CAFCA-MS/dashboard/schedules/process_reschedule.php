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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
$new_schedule_date = $_POST['schedule_date'] ?? '';
$new_start_time = $_POST['start_time'] ?? '';
$new_end_time = $_POST['end_time'] ?? '';
$new_date_span = isset($_POST['date_span']) ? intval($_POST['date_span']) : 0;
$reschedule_reason = $_POST['reschedule_reason'] ?? '';
$redirect = $_POST['redirect'] ?? 'Approved';

if ($schedule_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit();
}

if (empty($new_schedule_date) || empty($new_start_time) || empty($new_end_time) || $new_date_span || empty($reschedule_reason)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

$date = DateTime::createFromFormat('Y-m-d', $new_schedule_date);
if (!$date || $date->format('Y-m-d') !== $new_schedule_date) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

$today = new DateTime();
$today->setTime(0, 0, 0);
if ($date < $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot reschedule to a past date']);
    exit();
}

if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $new_start_time) || 
    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $new_end_time)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit();
}

$check_sql = "SELECT id, status FROM schedules WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $schedule_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Schedule not found']);
    exit();
}

$check_stmt->close();

$update_sql = "UPDATE schedules 
               SET schedule_date = ?, 
                   start_time = ?, 
                   end_time = ?, 
                   date_span = ?,
                   reschedule_reason = ?,
                   rescheduled_at = NOW()
               WHERE id = ?";

$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("sssisi", $new_schedule_date, $new_start_time, $new_end_time, $new_date_span, $reschedule_reason, $schedule_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Schedule rescheduled successfully',
        'redirect' => $redirect
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update schedule: ' . $conn->error]);
}

$update_stmt->close();
$conn->close();

?>