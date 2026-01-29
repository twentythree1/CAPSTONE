<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

$id = ""; 
$farmer_id = "";
$machine_id = "";
$harvest_date = "";
$number_of_sacks = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!isset($_GET["id"])) {
        header("location: /CAPSTONE/CAFCA-MS/dashboard/records/records.php");
        exit;
    }

    $id = $_GET["id"];

    $sql = "SELECT * FROM records WHERE id=$id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if(!$row) {
        header("location: /CAPSTONE/CAFCA-MS/dashboard/records/records.php");
        exit;
    }

    $farmer_id = $row["farmer_id"];
    $machine_id = $row["machine_id"];
    $harvest_date = $row["harvest_date"];
    $number_of_sacks = $row["number_of_sacks"];
}

else {
    $id = $_POST["id"];
    $farmer_id = $_POST["farmer_id"];
    $machine_id = $_POST["machine_id"];
    $harvest_date = $_POST["harvest_date"];
    $number_of_sacks = $_POST["number_of_sacks"];

    do {
        if (empty($id) || empty($farmer_id) || empty($machine_id)|| empty($harvest_date) || empty($number_of_sacks)){
            $errorMessage = "All fields are required!";
            break;
        }

        $sql = "UPDATE records " . 
            "SET farmer_id = '$farmer_id', machine_id = '$machine_id', harvest_date = '$harvest_date', number_of_sacks = '$number_of_sacks'" .
            "WHERE id = $id";

        $result = $conn->query($sql);
        
        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $successMessage = "Record successfully updated!";

        header("location: /CAPSTONE/CAFCA-MS/dashboard/records/records.php");
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
    <title>CAFCA | Record</title>
    <link rel="stylesheet" href="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    
</head>
<body>
    
    <div class="container my-5">
        <h2>Edit Records</h2>

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
                <label class="col-sm-3 col-form-label">Harvest Date</label>
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
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class="btn btn-outline-primary" href="/CAPSTONE/CAFCA-MS/dashboard/records/records.php" role="button">Cancel Editing</a>
                </div>
            </div>
        </form>
    </div>

    
</body>
</html>