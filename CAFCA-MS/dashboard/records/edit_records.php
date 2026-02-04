<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$farmerList = $conn->query("SELECT id, name FROM farmers");
$machineList = $conn->query("SELECT id, name FROM machines");

$id = ""; 
$farmer_id = "";
$machine_id = "";
$harvest_start_date = "";
$harvest_end_date = "";
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
    $harvest_start_date = $row["harvest_start_date"];
    $harvest_end_date = $row["harvest_end_date"];
    $number_of_sacks = $row["number_of_sacks"];
}
else {
    $id = $_POST["id"] ?? "";
    $farmer_id = $_POST["farmer_id"] ?? "";
    $machine_id = $_POST["machine_id"] ?? "";
    $harvest_start_date = $_POST["harvest_start_date"] ?? "";
    $harvest_end_date = $_POST["harvest_end_date"] ?? "";
    $number_of_sacks = $_POST["number_of_sacks"] ?? "";

    do {
        if (empty($id) || empty($farmer_id) || empty($machine_id) || empty($harvest_start_date) || empty($harvest_end_date) || empty($number_of_sacks)){
            $errorMessage = "All fields are required!";
            break;
        }

        if (strtotime($harvest_end_date) < strtotime($harvest_start_date)) {
            $errorMessage = "End date cannot be earlier than start date.";
            break;
        }

        $farmer_id_esc = (int)$farmer_id;
        $farmerCheck = $conn->query("SELECT id FROM farmers WHERE id = '$farmer_id_esc'");
        if ($farmerCheck->num_rows == 0) {
            $errorMessage = "Selected farmer does not exist.";
            break;
        }

        $machine_id_esc = (int)$machine_id;
        $machineCheck = $conn->query("SELECT id FROM machines WHERE id = '$machine_id_esc'");
        if ($machineCheck->num_rows == 0) {
            $errorMessage = "Selected machine does not exist.";
            break;
        }

        if (!is_numeric($number_of_sacks) || $number_of_sacks <= 0) {
            $errorMessage = "Number of sacks must be a positive number.";
            break;
        }

        $id_esc = (int)$id;
        $harvest_start_date_esc = $conn->real_escape_string($harvest_start_date);
        $harvest_end_date_esc = $conn->real_escape_string($harvest_end_date);
        $number_of_sacks_esc = (int)$number_of_sacks;

        $sql = "UPDATE records 
                SET farmer_id = '$farmer_id_esc', 
                    machine_id = '$machine_id_esc', 
                    harvest_start_date = '$harvest_start_date_esc', 
                    harvest_end_date = '$harvest_end_date_esc', 
                    number_of_sacks = '$number_of_sacks_esc' 
                WHERE id = $id_esc";

        $result = $conn->query($sql);
        
        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $successMessage = "Record successfully updated!";

        header("location: /CAPSTONE/CAFCA-MS/dashboard/records/records.php");
        exit;

    } while (false);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Record</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    
</head>
<body>
    
    <div class="container my-5">
        <h2>Edit Record</h2>

        <?php
        if (!empty($errorMessage)) {
            echo "
            <div class='alert alert-warning alert-dismissible fade show' role='alert'>
                <strong>" . htmlspecialchars($errorMessage) . "</strong>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
            ";
        }
        ?>
        
        <form method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
            
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Farmer</label>
                <div class="col-sm-6">
                    <select name="farmer_id" required class="form-select">
                        <option value="">Select a Farmer</option>
                        <?php
                        if ($farmerList === false || $farmerList->num_rows == 0) {
                            $farmerList = $conn->query("SELECT id, name FROM farmers");
                        }
                        while ($row = $farmerList->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= $farmer_id == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Machine</label>
                <div class="col-sm-6">
                    <select name="machine_id" required class="form-select">
                        <option value="">Select a Machine</option>
                        <?php
                        if ($machineList === false || $machineList->num_rows == 0) {
                            $machineList = $conn->query("SELECT id, name FROM machines");
                        }
                        while ($row = $machineList->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= $machine_id == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Harvest Start Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="harvest_start_date" 
                           value="<?= htmlspecialchars($harvest_start_date) ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Harvest End Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="harvest_end_date" 
                           value="<?= htmlspecialchars($harvest_end_date) ?>" required>
                </div>
            </div>
            
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Number of Sacks</label>
                <div class="col-sm-6">
                    <input type="number" step="1" min="1" class="form-control" 
                           name="number_of_sacks" 
                           value="<?= htmlspecialchars($number_of_sacks) ?>" required>
                </div>
            </div>

            <?php 
            if (!empty($successMessage)) {
                echo "
                <div class='row mb-3'>
                    <div class='offset-sm-3 col-sm-6'>
                        <div class='alert alert-success alert-dismissible fade show' role='alert'>
                            <strong>" . htmlspecialchars($successMessage) . "</strong>
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
                    <a class="btn btn-outline-primary" 
                       href="/CAPSTONE/CAFCA-MS/dashboard/records/records.php" 
                       onclick="return confirm('Are you sure you want to cancel editing?');" 
                       role="button">Cancel</a>
                </div>
            </div>
        </form>
    </div>

</body>
</html>
<?php
$conn->close();
?>