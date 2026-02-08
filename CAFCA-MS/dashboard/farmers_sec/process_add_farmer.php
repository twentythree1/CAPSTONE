<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$name = "";
$birthday = "";
$address = "";
$land = "";
$unit = "";
$phone = "";

$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST["name"]);
    $birthday = trim($_POST["birthday"]);
    $address = trim($_POST["address"]);
    $land = trim($_POST["land"]);
    $unit = trim($_POST["unit"]);
    $phone = trim($_POST["phone"]);

    do {
        if (empty($name) || empty($birthday) || empty($address) || empty($land) || empty($unit) || empty($phone)) {
            $errorMessage = "All fields are required!";
            break;
        }

        if (!preg_match("/^[a-zA-Z\s\.\-']+$/", $name)) {
            $errorMessage = "Name contains invalid characters.";
            break;
        }

        $birthdayDate = DateTime::createFromFormat('Y-m-d', $birthday);
        if (!$birthdayDate) {
            $errorMessage = "Invalid date format.";
            break;
        }

        $today = new DateTime();
        $age = $birthdayDate->diff($today)->y;

        if ($age < 15) {
            $errorMessage = "Farmer must be at least 15 years old.";
            break;
        }

        if (!is_numeric($land) || $land <= 0) {
            $errorMessage = "Land area must be a positive number.";
            break;
        }

        $validUnits = ['cm²', 'm²', 'km²', 'hectare(s)', 'acre(s)'];
        if (!in_array($unit, $validUnits)) {
            $errorMessage = "Invalid unit of measurement selected.";
            break;
        }

        if (!preg_match("/^[\d\s\-\(\)]+$/", $phone)) {
            $errorMessage = "Phone number contains invalid characters.";
            break;
        }

        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        
        if (strlen($cleanPhone) != 11 || !preg_match('/^09\d{9}$/', $cleanPhone)) {
            $errorMessage = "Phone number must be 11 digits starting with 09 (e.g., 09123456789).";
            break;
        }

        $sql = "INSERT INTO farmers (name, birthday, address, land, unit, phone) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
            break;
        }

        $stmt->bind_param("sssdss", $name, $birthday, $address, $land, $unit, $phone);

        if (!$stmt->execute()) {
            $errorMessage = "Error adding farmer: " . $stmt->error;
            $stmt->close();
            break;
        }

        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Farmer successfully added!']);
        $conn->close();
        exit;

    } while (false);

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    $conn->close();
    exit;
}

$conn->close();

header("location: /CAPSTONE/CAFCA-MS/dashboard/farmers_sec/farmers.php");
exit;
?>