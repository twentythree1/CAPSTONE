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

$machine_id = $_POST['machine_id'] ?? '';
$schedule_date = $_POST['schedule_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$exclude_schedule_id = $_POST['exclude_schedule_id'] ?? null; // For edit mode

if (empty($machine_id) || empty($schedule_date) || empty($end_date) || empty($start_time) || empty($end_time)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get the total quantity of this machine
$machine_id_esc = $conn->real_escape_string($machine_id);
$quantityQuery = "SELECT quantity FROM machines WHERE id = '$machine_id_esc'";
$quantityResult = $conn->query($quantityQuery);

if (!$quantityResult || $quantityResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Machine not found']);
    exit;
}

$machineData = $quantityResult->fetch_assoc();
$totalQuantity = (int)$machineData['quantity'];

// Count how many of this machine are already booked for the overlapping date range
$schedule_date_esc = $conn->real_escape_string($schedule_date);
$end_date_esc = $conn->real_escape_string($end_date);
$start_time_esc = $conn->real_escape_string($start_time);
$end_time_esc = $conn->real_escape_string($end_time);

$conflictQuery = "
    SELECT COUNT(*) as booked_count FROM schedules 
    WHERE machine_id = '$machine_id_esc'
      AND status IN ('Pending', 'Approved', 'On going')
      AND (
            DATE_ADD(schedule_date, INTERVAL date_span DAY) >= '$schedule_date_esc'
            AND schedule_date <= '$end_date_esc'
        )
      AND (
            (start_time < '$end_time_esc' AND end_time > '$start_time_esc')
        )
";

// If editing an existing schedule, exclude it from the count
if ($exclude_schedule_id !== null) {
    $exclude_id_esc = $conn->real_escape_string($exclude_schedule_id);
    $conflictQuery .= " AND id != '$exclude_id_esc'";
}

$conflictResult = $conn->query($conflictQuery);

if (!$conflictResult) {
    echo json_encode(['success' => false, 'message' => 'Error checking availability']);
    exit;
}

$conflictData = $conflictResult->fetch_assoc();
$bookedCount = (int)$conflictData['booked_count'];
$availableCount = $totalQuantity - $bookedCount;

echo json_encode([
    'success' => true,
    'total' => $totalQuantity,
    'booked' => $bookedCount,
    'available' => $availableCount
]);

$conn->close();
?>