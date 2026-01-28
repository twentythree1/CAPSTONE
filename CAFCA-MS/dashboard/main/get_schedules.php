<?php

$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT schedule_date, start_time, end_time FROM schedules";
$result = $conn->query($sql);

$schedules = [];

while ($row = $result->fetch_assoc()) {
    $schedules[] = [
        "date" => $row["schedule_date"],
        "title" => "📍 " . substr($row["start_time"], 0, 5) . " - " . substr($row["end_time"], 0, 5)
    ];
}

echo json_encode($schedules);
?>