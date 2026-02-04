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
    $cancelUrl = '/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Pending';
} else {
    $san = filter_var($rawRedirect, FILTER_SANITIZE_STRING);

    if (strpos($san, 'schedule.php') !== false) {
        if (strpos($san, '/CAPSTONE/CAFCA-MS/dashboard/schedules/') === false) {
            $cancelUrl = '/CAPSTONE/CAFCA-MS/dashboard/schedules/' . $san;
        } else {
            $cancelUrl = $san;
        }
    } else {
        $cancelUrl = '/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=' . urlencode($san);
    }
}

$farmerList = $conn->query("SELECT id, name FROM farmers");
$machineList = $conn->query("SELECT id, name FROM machines");

$farmer_id = "";
$machine_id = "";
$schedule_date = "";
$end_date = "";
$start_time = "";
$end_time = "";

$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $farmer_id = $_POST["farmer_id"] ?? "";
    $machine_id = $_POST["machine_id"] ?? "";
    $schedule_date = $_POST["schedule_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";
    $start_time = $_POST["start_time"] ?? "";
    $end_time = $_POST["end_time"] ?? "";

    if (
        empty($farmer_id) ||
        empty($machine_id) ||
        empty($schedule_date) ||
        empty($end_date) ||
        empty($start_time) ||
        empty($end_time)
    ) {
        $errorMessage = "All fields are required!";
    } else {
        $date_span = (int) floor((strtotime($end_date) - strtotime($schedule_date)) / (60 * 60 * 24));

        if ($date_span < 0) {
            $errorMessage = "End date cannot be earlier than start date.";
        } else {
            $conflictQuery = "
                SELECT * FROM schedules 
                WHERE machine_id = '$machine_id'
                  AND (
                        DATE_ADD(schedule_date, INTERVAL date_span DAY) >= '$schedule_date'
                        AND schedule_date <= '$end_date'
                    )
                  AND (
                        (start_time < '$end_time' AND end_time > '$start_time')
                    )
            ";

            $conflictResult = $conn->query($conflictQuery);
            if ($conflictResult && $conflictResult->num_rows > 0) {
                $errorMessage = "Machine is already scheduled during this date/time.";
            } else {
                $schedule_date_esc = $conn->real_escape_string($schedule_date);
                $start_time_esc = $conn->real_escape_string($start_time);
                $end_time_esc = $conn->real_escape_string($end_time);
                $farmer_id = (int)$farmer_id;
                $machine_id = (int)$machine_id;
                $date_span = (int)$date_span;

                $sql = "INSERT INTO schedules (farmer_id, machine_id, schedule_date, date_span, start_time, end_time, status) 
                        VALUES ('$farmer_id', '$machine_id', '$schedule_date_esc', '$date_span', '$start_time_esc', '$end_time_esc', 'Pending')";
                $result = $conn->query($sql);

                if (!$result) {
                    $errorMessage = "Invalid query: " . $conn->error;
                } else {
                    $farmer_id = "";
                    $machine_id = "";
                    $schedule_date = "";
                    $end_date = "";
                    $start_time = "";
                    $end_time = "";

                    $successMessage = "Schedule successfully created!";
                    header("Location: " . $cancelUrl);
                    exit;
                }
            }
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
    <title>CAFCA | Schedules</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <div class="container my-5">
        <h2>Create a Schedule</h2>

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
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($rawRedirect) ?>">

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Farmer</label>
                <div class="col-sm-6">
                    <select name="farmer_id" required class="form-select">
                        <option value="">Select a Farmer</option>
                        <?php
                        if ($farmerList === false) {
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
                        if ($machineList === false) {
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
                <label class="col-sm-3 col-form-label">Schedule Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="schedule_date" min="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($schedule_date) ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Schedule End Date</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" name="end_date" min="<?= date('Y-m-d') ?>"
                        value="<?= htmlspecialchars($end_date) ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Start Time</label>
                <div class="col-sm-6">
                    <input type="time" class="form-control" name="start_time"
                        value="<?= htmlspecialchars($start_time) ?>">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">End Time</label>
                <div class="col-sm-6">
                    <input type="time" class="form-control" name="end_time" value="<?= htmlspecialchars($end_time) ?>">
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
                    <a class="btn btn-outline-primary" href="<?= htmlspecialchars($cancelUrl) ?>"
                        onclick="return confirm('Are you sure you want to cancel?');" role="button">Cancel</a>
                </div>
            </div>
        </form>
    </div>

</body>

</html>