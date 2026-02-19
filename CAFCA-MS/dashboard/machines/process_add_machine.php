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
$unavailable_from = $_POST["unavailable_from"] ?? null;
$unavailable_until = $_POST["unavailable_until"] ?? null;
$redirect = $_POST["redirect"] ?? "Available";

// Validate required fields
if (empty($name) || empty($quantity) || empty($acquisition_date)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled!']);
    exit;
}

// Validate unavailable dates if provided
if (!empty($unavailable_from) && !empty($unavailable_until)) {
    $fromDate = new DateTime($unavailable_from);
    $untilDate = new DateTime($unavailable_until);
    
    if ($fromDate >= $untilDate) {
        echo json_encode(['success' => false, 'message' => 'Unavailable Until date must be after Unavailable From date']);
        exit;
    }
}

// Sanitize inputs
$name = $conn->real_escape_string(trim($name));
$quantity = intval($quantity); // Convert to integer
$acquisition_date = $conn->real_escape_string($acquisition_date);

// Automatically set status to "Available"
$status = "Available";

// Prepare SQL based on whether unavailable dates are provided
if (!empty($unavailable_from) && !empty($unavailable_until)) {
    $unavailable_from = $conn->real_escape_string($unavailable_from);
    $unavailable_until = $conn->real_escape_string($unavailable_until);
    
    $sql = "INSERT INTO machines (name, quantity, status, acquisition_date, unavailable_from, unavailable_until) 
            VALUES ('$name', $quantity, '$status', '$acquisition_date', '$unavailable_from', '$unavailable_until')";
} elseif (!empty($unavailable_from)) {
    $unavailable_from = $conn->real_escape_string($unavailable_from);
    
    $sql = "INSERT INTO machines (name, quantity, status, acquisition_date, unavailable_from) 
            VALUES ('$name', $quantity, '$status', '$acquisition_date', '$unavailable_from')";
} elseif (!empty($unavailable_until)) {
    $unavailable_until = $conn->real_escape_string($unavailable_until);
    
    $sql = "INSERT INTO machines (name, quantity, status, acquisition_date, unavailable_until) 
            VALUES ('$name', $quantity, '$status', '$acquisition_date', '$unavailable_until')";
} else {
    $sql = "INSERT INTO machines (name, quantity, status, acquisition_date) 
            VALUES ('$name', $quantity, '$status', '$acquisition_date')";
}

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