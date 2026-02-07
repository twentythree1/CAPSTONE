<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

// Handle redirect parameter
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

$id = "";
$name = "";
$type = "";
$status = "";
$acquisition_date = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!isset($_GET["id"])) {
        header("Location: " . $cancelUrl);
        exit;
    }

    $id = $_GET["id"];

    $sql = "SELECT * FROM machines WHERE id=$id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if (!$row) {
        header("Location: " . $cancelUrl);
        exit;
    }

    $name = $row["name"];
    $type = $row["type"];
    $status = $row["status"];
    $acquisition_date = $row["acquisition_date"];
} else {
    $id = $_POST["id"];
    $name = $_POST["name"];
    $type = $_POST["type"];
    $status = $_POST["status"];
    $acquisition_date = $_POST["acquisition_date"];

    do {
        if (empty($id) || empty($name) || empty($type) || empty($status) || empty($acquisition_date)) {
            $errorMessage = "All fields are required!";
            break;
        }

        $sql = "UPDATE machines " .
            "SET name = '$name', type = '$type', status = '$status', acquisition_date = '$acquisition_date'" .
            "WHERE id = $id";

        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $successMessage = "Machine's information successfully updated!";

        header("Location: " . $cancelUrl);
        exit;

    } while (true);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Farmers</title>
    <link rel="stylesheet" href="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <div class="container my-5">
        <h2>Edit machine's information</h2>

        <?php
        if (!empty($errorMessage)) {
            echo "
            <div class='alert alert-warning alert-dismissible fade show' role='alert'>
                <strong>$errorMessage</strong>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
            ";
        }
        ?>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($rawRedirect) ?>">
            
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Name</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="name" value="<?php echo $name; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Type</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="type" value="<?php echo $type; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Status</label>
                <div class="col-sm-6">
                    <select type="text" class="form-select" name="status">
                        <option value="N/A">---</option>
                        <option value="Available" <?= $status == 'Available' ? 'selected' : '' ?>>Available</option>
                        <option value="Partially Damaged" <?= $status == 'Partially Damaged' ? 'selected' : '' ?>>Partially Damaged</option>
                        <option value="Damaged" <?= $status == 'Damaged' ? 'selected' : '' ?>>Damaged</option>
                        <option value="Totally Damaged" <?= $status == 'Totally Damaged' ? 'selected' : '' ?>>Totally Damaged</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Acquisition Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="acquisition_date"
                        value="<?php echo $acquisition_date; ?>">
                </div>
            </div>



            <?php
            if (!empty($successMessage)) {
                echo "
                <div class='row mb-3'>
                    <div class='offset-sm-3 col-sm-6'>
                        <div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <strong>$successMessage</strong>
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>
                    </div>
                </div>
                ";
            }
            ?>
            <div class="row mb-3">
                <div class="offset-sm-3 col-sm-3 d-grid">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class="btn btn-outline-primary" href="<?= htmlspecialchars($cancelUrl) ?>"
                        role="button">Cancel Editing</a>
                </div>
            </div>
        </form>
    </div>


</body>

</html>