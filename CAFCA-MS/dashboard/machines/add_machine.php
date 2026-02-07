<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

$rawRedirect = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rawRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : null;
} else {
    $rawRedirect = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_GET['redirect']) ? $_GET['redirect'] : null);
}
$rawRedirect = is_string($rawRedirect) ? trim($rawRedirect) : '';

if ($rawRedirect === '') {
    $cancelUrl = '/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=Available';
} else {
    $san = filter_var($rawRedirect, FILTER_SANITIZE_STRING);

    if (strpos($san, 'machine.php') !== false) {
        if (strpos($san, '/CAPSTONE/CAFCA-MS/dashboard/machines/') === false) {
            $cancelUrl = '/CAPSTONE/CAFCA-MS/dashboard/machines/' . $san;
        } else {
            $cancelUrl = $san;
        }
    } else {
        $cancelUrl = '/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php?status=' . urlencode($san);
    }
}

$name = "";
$type = "";
$acquisition_date = "";

$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST["name"];
    $type = $_POST["type"];
    $acquisition_date = $_POST["acquisition_date"];

    if (empty($name) || empty($type) || empty($acquisition_date)) {
        $errorMessage = "All fields are required!";
    } else {
        // Automatically set status to "Available"
        $status = "Available";

        $sql = "INSERT INTO machines (name, type, status, acquisition_date) 
                VALUES ('$name', '$type', '$status', '$acquisition_date')";
        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Error inserting machine: " . $conn->error;
        } else {
            // Reset fields after successful addition
            $name = "";
            $type = "";
            $acquisition_date = "";

            $successMessage = "Machine successfully added with status 'Available'!";
            header("Location: " . $cancelUrl);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Add Machine</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="container my-5">
        <h2 class="mb-4">Add a New Machine</h2>

        <!-- Display error or success messages -->
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Machine Addition Form -->
        <form method="post">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($rawRedirect) ?>">

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Machine Name</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($name) ?>" 
                           placeholder="Enter machine name" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Type</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="type" value="<?= htmlspecialchars($type) ?>" 
                           placeholder="Enter machine type (e.g., tractor, harvester)" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Acquisition Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="acquisition_date" value="<?= htmlspecialchars($acquisition_date) ?>" 
                           placeholder="Select acquisition date" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="offset-sm-3 col-sm-3 d-grid">
                    <button type="submit" class="btn btn-primary">Add Machine</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($cancelUrl) ?>" role="button">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</body>

</html>