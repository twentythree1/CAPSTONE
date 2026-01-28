<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

$id = "";
$farmer_id = "";
$machine_id = "";
$schedule_date = "";
$date_span = "";
$start_time = "";
$end_time = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!isset($_GET["id"])) {
        header("location: /CAFCA-MS/dashboard/schedules/schedule.php");
        exit;
    }

    $id = $_GET["id"];

    $sql = "SELECT * FROM schedules WHERE id=$id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if (!$row) {
        header("location: /CAFCA-MS/dashboard/schedules/schedule.php");
        exit;
    }

    $farmer_id = $row["farmer_id"];
    $machine_id = $row["machine_id"];
    $schedule_date = $row["schedule_date"];
    $date_span = $row["date_span"];
    $start_time = $row["start_time"];
    $end_time = $row["end_time"];

} else {

    $id = isset($_POST["id"]) ? $_POST["id"] : '';
    $farmer_id = $_POST["farmer_id"];
    $machine_id = $_POST["machine_id"];
    $schedule_date = $_POST["schedule_date"];
    $date_span = $_POST["date_span"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];

    $farmer_name = "";
    $machine_name = "";

    $farmRes = $conn->query("SELECT name FROM farmers WHERE id = '$farmer_id'");
    if ($farmRes && $farmRes->num_rows > 0) {
        $farmer_name = $farmRes->fetch_assoc()['name'];
    }

    $machRes = $conn->query("SELECT name FROM machines WHERE id = '$machine_id'");
    if ($machRes && $machRes->num_rows > 0) {
        $machine_name = $machRes->fetch_assoc()['name'];
    }

    do {
        if (
            empty($id) || empty($farmer_id) || empty($machine_id) ||
            empty($schedule_date) || empty($date_span) ||
            empty($start_time) || empty($end_time)
        ) {
            $errorMessage = "All fields are required!";
            break;
        }


        $sql = "UPDATE schedules 
                SET farmer_id = '$farmer_id', machine_id = '$machine_id', schedule_date = '$schedule_date', date_span = '$date_span', start_time = '$start_time', end_time = '$end_time' 
                WHERE id = $id";

        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $successMessage = "Schedule successfully updated!";
        header("location: /CAFCA-MS/dashboard/schedules/schedule.php");
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
        <h2>Edit Schedule</h2>

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
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Farmer</label>
                <div class="col-sm-6">
                    <select name="farmer_id" class="form-select" required>
                        <option value="">Select a Farmer</option>
                        <?php
                        $farmerList = $conn->query("SELECT id, name FROM farmers");
                        while ($row = $farmerList->fetch_assoc()):
                            ?>
                            <option value="<?= $row['id'] ?>" <?= $row['id'] == $farmer_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Machine</label>
                <div class="col-sm-6">
                    <select name="machine_id" class="form-select" required>
                        <option value="">Select a Machine</option>
                        <?php
                        $machineList = $conn->query("SELECT id, name FROM machines");
                        while ($row = $machineList->fetch_assoc()):
                            ?>
                            <option value="<?= $row['id'] ?>" <?= $row['id'] == $machine_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Schedule Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="schedule_date" value="<?php echo $schedule_date; ?>" min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Date Span</label>
                <div class="col-sm-6">
                    <input type="number" class="form-control" name="date_span" value="<?php echo $date_span; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Start Time</label>
                <div class="col-sm-6">
                    <input type="time" class="form-control" name="start_time" value="<?php echo $start_time; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">End Time</label>
                <div class="col-sm-6">
                    <input type="time" class="form-control" name="end_time" value="<?php echo $end_time; ?>">
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
                    <a class="btn btn-outline-primary" href="/CAFCA-MS/dashboard/schedules/schedule.php?status=Completed"
                        role="button">Cancel Editing</a>
                </div>
            </div>
        </form>
    </div>


</body>

</html>