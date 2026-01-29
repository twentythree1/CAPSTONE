<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);


$name = "";
$type = "";
$status = "";
$acquisition_date = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST["name"];
    $type = $_POST["type"];
    $status = $_POST["status"];
    $acquisition_date = $_POST["acquisition_date"];


    do {
        if (empty($name) || empty($type) || empty($status) || empty($acquisition_date)){
            $errorMessage = "All fields are required!";
            break;
        }

        // adding machine
        $sql = "INSERT INTO machines (name, type, status, acquisition_date)" . "VALUES ('$name', '$type', '$status', '$acquisition_date')";
        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $name = "";
        $type = "";
        $status = "";
        $acquisition_date = "";

        $successMessage = "Machine successfully added!";

        header("location: /CAFCA-MS/dashboard/machines/machine.php");
        exit;

    } while (false);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Machines</title>
    <link rel="stylesheet" href="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    
</head>
<body>
    
    <div class="container my-5">
        <h2>Add a Machine</h2>

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
                <label class="col-sm-3 col-form-label">Acquisition Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="acquisition_date" value="<?php echo $acquisition_date; ?>">
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
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class="btn btn-outline-primary" href="/CAPSTONE/CAFCA-MS/dashboard/machines/machine.php" role="button">Cancel</a>
                </div>
            </div>
        </form>
    </div>

</body>
</html>