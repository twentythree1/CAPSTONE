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

$name = $_POST["name"] ?? "";
$quantity = $_POST["quantity"] ?? "";
$acquisition_date = $_POST["acquisition_date"] ?? "";
$redirect = $_POST["redirect"] ?? "Available";

// Validate required fields
if (empty($name) || empty($quantity) || empty($acquisition_date)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required!']);
    exit;
}

// Sanitize inputs
$name = $conn->real_escape_string(trim($name));
$quantity = intval($quantity); // Convert to integer
$acquisition_date = $conn->real_escape_string($acquisition_date);

// Automatically set status to "Available"
$status = "Available";

// Insert new machine
$sql = "INSERT INTO machines (name, quantity, status, acquisition_date) 
        VALUES ('$name', $quantity, '$status', '$acquisition_date')";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Error inserting machine: ' . $conn->error]);
    exit;
}

echo json_encode([
    'success' => true, 
    'message' => 'Machine successfully added with status "Available"!',
    'redirect' => $redirect
]);

$conn->close();
?>