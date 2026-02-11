<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed"]);
    exit;
}

$sql = "
    SELECT 
        s.schedule_date,
        s.start_time,
        s.end_time,
        f.name AS farmer_name
    FROM schedules s
    LEFT JOIN farmers f ON s.farmer_id = f.id
    WHERE s.status IN ('Approved', 'On going', 'Completed')
";

$result = $conn->query($sql);

$schedules = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = [
            "date" => $row["schedule_date"],
            "title" => "📍 " . substr($row["start_time"], 0, 5) . " - " . substr($row["end_time"], 0, 5),
            "farmer_name" => $row["farmer_name"] ?? ""
        ];
    }
}

echo json_encode($schedules);