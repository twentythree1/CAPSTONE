<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$id = "";
$name = "";
$birthday = "";
$address = "";
$land = "";
$unit = "";
$phone = "";

$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = trim($_POST["id"]);
    $name = trim($_POST["name"]);
    $birthday = trim($_POST["birthday"]);
    $address = trim($_POST["address"]);
    $land = trim($_POST["land"]);
    $unit = trim($_POST["unit"]);
    $phone = trim($_POST["phone"]);

    do {
        // Check if all fields are filled
        if (empty($id) || empty($name) || empty($birthday) || empty($address) || empty($land) || empty($unit) || empty($phone)) {
            $errorMessage = "All fields are required!";
            break;
        }

        // Validate name
        if (!preg_match("/^[a-zA-Z\s\.\-']+$/", $name)) {
            $errorMessage = "Name contains invalid characters.";
            break;
        }

        // Validate birthday
        $birthdayDate = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$birthdayDate) {
            $errorMessage = "Invalid date format.";
            break;
        }

        // Check age
        $today = new DateTime();
        $age = $birthdayDate->diff($today)->y;

        if ($age < 15) {
            $errorMessage = "Farmer must be at least 15 years old.";
            break;
        }

        // Validate land area
        if (!is_numeric($land) || $land <= 0) {
            $errorMessage = "Land area must be a positive number.";
            break;
        }

        // Validate unit
        $validUnits = ['cm²', 'm²', 'km²', 'hectare(s)', 'acre(s)'];
        if (!in_array($unit, $validUnits)) {
            $errorMessage = "Invalid unit of measurement selected.";
            break;
        }

        // Validate phone number format
        if (!preg_match("/^[\d\s\-\(\)]+$/", $phone)) {
            $errorMessage = "Phone number contains invalid characters.";
            break;
        }

        // Remove non-numeric characters for validation
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        
        // Check if it's exactly 11 digits and starts with 09 (Philippine mobile format)
        if (strlen($cleanPhone) != 11 || !preg_match('/^09\d{9}$/', $cleanPhone)) {
            $errorMessage = "Phone number must be 11 digits starting with 09 (e.g., 09123456789).";
            break;
        }

        // Use prepared statement to prevent SQL injection
        $sql = "UPDATE farmers SET name = ?, birthday = ?, address = ?, land = ?, unit = ?, phone = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
            break;
        }

        $stmt->bind_param("sssdssi", $name, $birthday, $address, $land, $unit, $phone, $id);

        if (!$stmt->execute()) {
            $errorMessage = "Error updating farmer: " . $stmt->error;
            $stmt->close();
            break;
        }

        $stmt->close();

        // Success - return JSON response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Farmer successfully updated!']);
        $conn->close();
        exit;

    } while (false);

    // If we reach here, there was an error - return JSON response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    $conn->close();
    exit;
}

$conn->close();

// If accessed directly (not via POST), redirect to farmers page
header("location: /CAPSTONE/CAFCA-MS/dashboard/farmers_sec/farmers.php");
exit;
?>