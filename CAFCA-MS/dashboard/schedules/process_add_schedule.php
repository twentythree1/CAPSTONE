<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$farmer_id = $_POST["farmer_id"] ?? "";
$machine_id = $_POST["machine_id"] ?? "";
$schedule_date = $_POST["schedule_date"] ?? "";
$end_date = $_POST["end_date"] ?? "";
$start_time = $_POST["start_time"] ?? "";
$end_time = $_POST["end_time"] ?? "";
$redirect = $_POST["redirect"] ?? "Pending";

if (
    empty($farmer_id) ||
    empty($machine_id) ||
    empty($schedule_date) ||
    empty($end_date) ||
    empty($start_time) ||
    empty($end_time)
) {
    echo json_encode(['success' => false, 'message' => 'All fields are required!']);
    exit;
}

$date_span = (int) floor((strtotime($end_date) - strtotime($schedule_date)) / (60 * 60 * 24));

if ($date_span < 0) {
    echo json_encode(['success' => false, 'message' => 'End date cannot be earlier than start date.']);
    exit;
}

$machine_id_esc = $conn->real_escape_string($machine_id);
$schedule_date_esc = $conn->real_escape_string($schedule_date);
$end_date_esc = $conn->real_escape_string($end_date);
$start_time_esc = $conn->real_escape_string($start_time);
$end_time_esc = $conn->real_escape_string($end_time);

$conflictQuery = "
    SELECT * FROM schedules 
    WHERE machine_id = '$machine_id_esc'
      AND (
            DATE_ADD(schedule_date, INTERVAL date_span DAY) >= '$schedule_date_esc'
            AND schedule_date <= '$end_date_esc'
        )
      AND (
            (start_time < '$end_time_esc' AND end_time > '$start_time_esc')
        )
";

$conflictResult = $conn->query($conflictQuery);
if ($conflictResult && $conflictResult->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Machine is already scheduled during this date/time.']);
    exit;
}

$farmer_id = (int)$farmer_id;
$machine_id = (int)$machine_id;
$date_span = (int)$date_span;

$sql = "INSERT INTO schedules (farmer_id, machine_id, schedule_date, date_span, start_time, end_time, status) 
        VALUES ('$farmer_id', '$machine_id', '$schedule_date_esc', '$date_span', '$start_time_esc', '$end_time_esc', 'Pending')";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Failed to create schedule: ' . $conn->error]);
    exit;
}

echo json_encode([
    'success' => true, 
    'message' => 'Schedule successfully created!',
    'redirect' => $redirect
]);

$conn->close();
?>