<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

// fetching
$farmerList = $conn->query("SELECT id, name FROM farmers");
$machineList = $conn->query("SELECT id, name FROM machines");

$farmer_id = "";
$machine_id = "";
$schedule_date = "";
$date_span = "";
$start_time = "";
$end_time = "";


$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $farmer_id = $_POST["farmer_id"];
    $machine_id = $_POST["machine_id"];
    $schedule_date = $_POST["schedule_date"];
    $date_span = $_POST["date_span"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];


    do {
        if (empty($farmer_id) || empty($machine_id) || empty($schedule_date) || empty($date_span) || empty($start_time) || empty($end_time)) {
            $errorMessage = "All fields are required!";
            break;
        }

        // Calculate end date based on schedule_date + date_span
        $end_date = date('Y-m-d', strtotime($schedule_date . " +$date_span days"));

        $conflictQuery = "
            SELECT * FROM schedules 
            WHERE machine_id = '$machine_id'
              AND (
                    -- Schedule ranges overlap
                    DATE_ADD(schedule_date, INTERVAL date_span DAY) > '$schedule_date'
                    AND schedule_date <= '$end_date'
                )
              AND (
                    -- Time overlaps
                    (start_time < '$end_time' AND end_time > '$start_time')
                )
        ";

        $conflictResult = $conn->query($conflictQuery);
        if ($conflictResult->num_rows > 0) {
            $errorMessage = "Machine is already scheduled during this date/time.";
            break;
        }

        // adding schedule
        $sql = "INSERT INTO schedules (farmer_id, machine_id, schedule_date, date_span, start_time, end_time)" . "VALUES ('$farmer_id', '$machine_id', '$schedule_date', '$date_span', '$start_time', '$end_time')";
        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $farmer_id = "";
        $machine_id = "";
        $schedule_date = "";
        $date_span = "";
        $start_time = "";
        $end_time = "";

        $successMessage = "Schedule successfully created!";

        header("location: /CAFCA-MS/dashboard/schedules/schedule.php?status=Pending");
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
    <title>CAFCA | Schedules</title>
    <link rel="stylesheet" href="	https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <div class="container my-5">
        <h2>Create a Schedule</h2>

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
                <label class="col-sm-3 col-form-label">Farmer</label>
                <div class="col-sm-6">
                    <select name="farmer_id" required type="text" class="form-select">
                        <option value="">Select a Farmer</option>
                        <?php while ($row = $farmerList->fetch_assoc()): ?>
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
                    <select name="machine_id" required type="text" class="form-select">
                        <option value="">Select a Machine</option>
                        <?php while ($row = $machineList->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= $machine_id == $row['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($row['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Schedule Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="schedule_date" min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo $schedule_date; ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Date Span (days)</label>
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
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class="btn btn-outline-primary" href="/CAFCA-MS/dashboard/schedules/schedule.php"
                        role="button">Cancel</a>
                </div>
            </div>
        </form>
    </div>

</body>

</html>