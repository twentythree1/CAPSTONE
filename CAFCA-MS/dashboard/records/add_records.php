<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);


$farmer_id = "";
$machine_id = "";
$harvest_date = "";
$number_of_sacks = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $farmer_id = $_POST["farmer_id"];
    $machine_id = $_POST["machine_id"];
    $harvest_date = $_POST["harvest_date"];
    $number_of_sacks = $_POST["number_of_sacks"];


    do {
        if (empty($farmer_id) || empty($machine_id) || empty($harvest_date) || empty($number_of_sacks)){
            $errorMessage = "All fields are required!";
            break;
        }

        // Check if farmer exists
        $farmerCheck = $conn->query("SELECT id FROM farmers WHERE id = '$farmer_id'");
        if ($farmerCheck->num_rows == 0) {
            $errorMessage = "Farmer ID does not exist.";
            break;
        }

        // Check if machine exists
        $machineCheck = $conn->query("SELECT id FROM machines WHERE id = '$machine_id'");
        if ($machineCheck->num_rows == 0) {
            $errorMessage = "Machine ID does not exist.";
            break;
        }

        // adding record
        $sql = "INSERT INTO records (farmer_id, machine_id, harvest_date, number_of_sacks)" . "VALUES ('$farmer_id', '$machine_id', '$harvest_date', '$number_of_sacks')";
        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $farmer_id = "";
        $machine_id = "";
        $harvest_date = "";
        $number_of_sacks = "";

        $successMessage = "Record successfully created!";

        header("location: /CAFCA-MS/dashboard/records/records.php");
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
    <title>CAFCA | Records</title>
    <link rel="stylesheet" href="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    
</head>
<body>
    
    <div class="container my-5">
        <h2>Create a Record</h2>

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
                <label class="col-sm-3 col-form-label">Farmer ID</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="farmer_id" value="<?php echo $farmer_id; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Machine ID</label>
                <div class="col-sm-6">
                    <input type="text" class="form-control" name="machine_id" value="<?php echo $machine_id; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Schedule Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="harvest_date" value="<?php echo $harvest_date; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Number of Sacks</label>
                <div class="col-sm-6">
                    <input type="number" step="1" class="form-control" name="number_of_sacks" value="<?php echo $number_of_sacks; ?>">
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
                    <a class="btn btn-outline-primary" href="/CAPSTONE/CAFCA-MS/dashboard/records/records.php" role="button">Cancel</a>
                </div>
            </div>
        </form>
    </div>

</body>
</html>