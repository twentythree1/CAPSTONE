<?php
session_start();

// Database connection
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

$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = trim($_POST["id"] ?? "");
    $farmer_id = trim($_POST["farmer_id"] ?? "");
    $rsbsa_reference_no = trim($_POST["rsbsa_reference_no"] ?? "");
    $ecosystem = trim($_POST["ecosystem"] ?? "");
    $variety_planted = trim($_POST["variety_planted"] ?? "");
    $area_harvested = trim($_POST["area_harvested"] ?? "");
    $gross_yield = trim($_POST["gross_yield"] ?? "");
    $avg_weight_per_sack = trim($_POST["avg_weight_per_sack"] ?? "");
    $total_yield = trim($_POST["total_yield"] ?? "");
    $avg_yield = trim($_POST["avg_yield"] ?? "");

    do {
        // Check if all fields are filled
        if (empty($id) || empty($farmer_id) || empty($rsbsa_reference_no) || empty($ecosystem) || 
            empty($variety_planted) || empty($area_harvested) || empty($gross_yield) || 
            empty($avg_weight_per_sack) || empty($total_yield) || empty($avg_yield)) {
            $errorMessage = "All fields are required!";
            break;
        }

        // Validate record ID
        if (!is_numeric($id)) {
            $errorMessage = "Invalid record ID.";
            break;
        }

        $id_int = (int)$id;

        // Validate farmer ID is numeric
        if (!is_numeric($farmer_id)) {
            $errorMessage = "Invalid farmer selection.";
            break;
        }

        $farmer_id_int = (int)$farmer_id;

        // Validate farmer exists
        $farmerCheckSql = "SELECT id FROM farmers WHERE id = ?";
        $farmerCheckStmt = $conn->prepare($farmerCheckSql);
        $farmerCheckStmt->bind_param("i", $farmer_id_int);
        $farmerCheckStmt->execute();
        $farmerCheckResult = $farmerCheckStmt->get_result();
        
        if ($farmerCheckResult->num_rows == 0) {
            $errorMessage = "Selected farmer does not exist.";
            $farmerCheckStmt->close();
            break;
        }
        $farmerCheckStmt->close();

        // Validate record exists
        $recordCheckSql = "SELECT id FROM records WHERE id = ?";
        $recordCheckStmt = $conn->prepare($recordCheckSql);
        $recordCheckStmt->bind_param("i", $id_int);
        $recordCheckStmt->execute();
        $recordCheckResult = $recordCheckStmt->get_result();
        
        if ($recordCheckResult->num_rows == 0) {
            $errorMessage = "Record not found.";
            $recordCheckStmt->close();
            break;
        }
        $recordCheckStmt->close();

        // Validate RSBSA reference number
        if (!preg_match("/^[a-zA-Z0-9\s\-]+$/", $rsbsa_reference_no)) {
            $errorMessage = "RSBSA Reference No. contains invalid characters.";
            break;
        }

        if (strlen($rsbsa_reference_no) > 100) {
            $errorMessage = "RSBSA Reference No. is too long (max 100 characters).";
            break;
        }

        // Validate ecosystem
        $validEcosystems = ['Irrigated', 'Rainfed'];
        if (!in_array($ecosystem, $validEcosystems)) {
            $errorMessage = "Invalid ecosystem selected.";
            break;
        }

        // Validate variety planted
        if (!preg_match("/^[a-zA-Z0-9\s\.\-]+$/", $variety_planted)) {
            $errorMessage = "Variety planted contains invalid characters.";
            break;
        }

        // Validate numeric fields
        if (!is_numeric($area_harvested) || $area_harvested <= 0) {
            $errorMessage = "Area harvested must be a positive number.";
            break;
        }

        if (!is_numeric($gross_yield) || $gross_yield <= 0) {
            $errorMessage = "Gross yield must be a positive number.";
            break;
        }

        if (!is_numeric($avg_weight_per_sack) || $avg_weight_per_sack <= 0) {
            $errorMessage = "Average weight per sack must be a positive number.";
            break;
        }

        if (!is_numeric($total_yield) || $total_yield <= 0) {
            $errorMessage = "Total yield must be a positive number.";
            break;
        }

        if (!is_numeric($avg_yield) || $avg_yield <= 0) {
            $errorMessage = "Average yield must be a positive number.";
            break;
        }

        // Update record using prepared statement
        $sql = "UPDATE records SET farmer_id = ?, rsbsa_reference_no = ?, ecosystem = ?, variety_planted = ?, 
                area_harvested = ?, gross_yield = ?, avg_weight_per_sack = ?, total_yield = ?, avg_yield = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
            break;
        }

        $stmt->bind_param("isssdddddi", $farmer_id_int, $rsbsa_reference_no, $ecosystem, $variety_planted, 
                          $area_harvested, $gross_yield, $avg_weight_per_sack, $total_yield, $avg_yield, $id_int);

        if (!$stmt->execute()) {
            $errorMessage = "Error updating record: " . $stmt->error;
            $stmt->close();
            break;
        }

        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Record successfully updated!']);
        $conn->close();
        exit;

    } while (false);

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    $conn->close();
    exit;
}

$conn->close();

header("location: /CAPSTONE/CAFCA-MS/dashboard/records/records.php");
exit;
?>