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
$quantity = "";
$status = "";
$acquisition_date = "";

$errorMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = trim($_POST["id"]);
    $name = trim($_POST["name"]);
    $quantity = trim($_POST["quantity"]);
    $status = trim($_POST["status"]);
    $acquisition_date = trim($_POST["acquisition_date"]);

    do {
        if (empty($id) || empty($name) || empty($quantity) || empty($status) || empty($acquisition_date)) {
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

        $sql = "UPDATE machines SET name = ?, quantity = ?, status = ?, acquisition_date = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
            break;
        }

        $quantity = intval($quantity); // Convert to integer
        $stmt->bind_param("sissi", $name, $quantity, $status, $acquisition_date, $id);

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