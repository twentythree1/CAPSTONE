<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

$id = "";
$name = "";
$type = "";
$status = "";
$acquisition_date = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!isset($_GET["id"])) {
        header("location: /CAPSTONE/CAFCA-MS/dashboard/machines/machine.php");
        exit;
    }

    $id = $_GET["id"];

    $sql = "SELECT * FROM machines WHERE id=$id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if (!$row) {
        header("location: /CAPSTONE/CAFCA-MS/dashboard/machines/machine.php");
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

        header("location:  /CAPSTONE/CAFCA-MS/dashboard/machines/machine.php");
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
                        <option value="Available">Available</option>
                        <option value="Partially Damaged">Partially Damaged</option>
                        <option value="Damaged">Damaged</option>
                        <option value="Totally Damaged">Totally Damaged</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Address</label>
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
                    <a class="btn btn-outline-primary" href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php"
                        role="button">Cancel Editing</a>
                </div>
            </div>
        </form>
    </div>


</body>

</html>