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

$id = "";
$name = "";
$status = "";
$acquisition_date = "";
$unavailable_from = null;
$unavailable_until = null;

$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = trim($_POST["id"]);
    $name = trim($_POST["name"]);
    $status = trim($_POST["status"]);
    $acquisition_date = trim($_POST["acquisition_date"]);
    $unavailable_from = !empty($_POST["unavailable_from"]) ? trim($_POST["unavailable_from"]) : null;
    $unavailable_until = !empty($_POST["unavailable_until"]) ? trim($_POST["unavailable_until"]) : null;

    do {
        if (empty($id) || empty($name) || empty($status) || empty($acquisition_date)) {
            $errorMessage = "All fields are required!";
            break;
        }

        if (!is_numeric($id)) {
            $errorMessage = "Invalid machine ID.";
            break;
        }

        $validStatuses = ['Available', 'Partially Damaged', 'Damaged', 'Totally Damaged'];
        if (!in_array($status, $validStatuses)) {
            $errorMessage = "Invalid status selected.";
            break;
        }

        $acquisitionDate = DateTime::createFromFormat('Y-m-d', $acquisition_date);
        if (!$acquisitionDate) {
            $errorMessage = "Invalid date format.";
            break;
        }

        $today = new DateTime();
        if ($acquisitionDate > $today) {
            $errorMessage = "Acquisition date cannot be in the future.";
            break;
        }

        // Validate unavailable dates if both are provided
        if ($unavailable_from !== null && $unavailable_until !== null) {
            try {
                $fromDate = new DateTime($unavailable_from);
                $untilDate = new DateTime($unavailable_until);
                
                if ($fromDate >= $untilDate) {
                    $errorMessage = "Unavailable Until date must be after Unavailable From date.";
                    break;
                }
            } catch (Exception $e) {
                $errorMessage = "Invalid unavailable date format.";
                break;
            }
        }

        $sql = "UPDATE machines SET name = ?, status = ?, acquisition_date = ?, unavailable_from = ?, unavailable_until = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
            break;
        }

        $stmt->bind_param("sssssi", $name, $status, $acquisition_date, $unavailable_from, $unavailable_until, $id);

        if (!$stmt->execute()) {
            $errorMessage = "Error updating machine: " . $stmt->error;
            $stmt->close();
            break;
        }

        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Machine successfully updated!']);
        $conn->close();
        exit;

    } while (false);

    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    $conn->close();
    exit;
}

$conn->close();

header("location: /CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Available");
exit;
?>