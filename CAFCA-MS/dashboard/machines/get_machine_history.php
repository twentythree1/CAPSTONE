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

$machine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$machine_id) {
    echo json_encode(['success' => false, 'message' => 'No machine ID provided']);
    exit;
}

// Fetch the machine name
$machineQuery = "SELECT name FROM machines WHERE id = ?";
$stmt = $conn->prepare($machineQuery);
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$machineResult = $stmt->get_result();
$machineRow = $machineResult->fetch_assoc();
$stmt->close();

if (!$machineRow) {
    echo json_encode(['success' => false, 'message' => 'Machine not found']);
    exit;
}

$machineName = $machineRow['name'];

// Fetch usage history for the machine
$historyQuery = "
    SELECT 
        schedules.schedule_date,
        schedules.date_span,
        farmers.name AS farmer_name
    FROM schedules
    JOIN farmers ON schedules.farmer_id = farmers.id
    WHERE schedules.machine_id = ?
    ORDER BY schedules.schedule_date DESC
";

$stmt = $conn->prepare($historyQuery);
$stmt->bind_param("i", $machine_id);
$stmt->execute();
$historyResult = $stmt->get_result();

$history = [];
while ($row = $historyResult->fetch_assoc()) {
    $startDate = date('Y-m-d', strtotime($row['schedule_date']));
    $endDate = date('Y-m-d', strtotime($row['schedule_date'] . " +{$row['date_span']} days"));
    
    $history[] = [
        'farmer_name' => $row['farmer_name'],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'total_days' => $row['date_span']
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'machine_name' => $machineName,
    'history' => $history
]);
?>