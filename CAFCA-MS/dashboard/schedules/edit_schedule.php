<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$rawRedirect = null;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rawRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : null;
} else {
    $rawRedirect = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_GET['redirect']) ? $_GET['redirect'] : null);
}
$rawRedirect = is_string($rawRedirect) ? trim($rawRedirect) : '';


if ($rawRedirect === '') {
    $cancelUrl = 'schedule.php';
} else {
    $san = filter_var($rawRedirect, FILTER_SANITIZE_STRING);

    if (strpos($san, 'status=') !== false || stripos($san, 'schedule.php') !== false || preg_match('#^/|https?://#i', $san)) {
        $cancelUrl = $san;
    } else {
        $cancelUrl = 'schedule.php?status=' . urlencode($san);
    }
}

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
        header("Location: " . $cancelUrl);
        exit;
    }

    $id = (int)$_GET["id"];

    $sql = "SELECT * FROM schedules WHERE id=$id";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        header("Location: " . $cancelUrl);
        exit;
    }

    $row = $result->fetch_assoc();

    $farmer_id = $row["farmer_id"];
    $machine_id = $row["machine_id"];
    $schedule_date = $row["schedule_date"];
    $date_span = $row["date_span"];
    $start_time = $row["start_time"];
    $end_time = $row["end_time"];

} else {
    $id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
    $farmer_id = isset($_POST["farmer_id"]) ? (int)$_POST["farmer_id"] : 0;
    $machine_id = isset($_POST["machine_id"]) ? (int)$_POST["machine_id"] : 0;
    $schedule_date = isset($_POST["schedule_date"]) ? $_POST["schedule_date"] : '';
    $date_span = isset($_POST["date_span"]) ? (int)$_POST["date_span"] : 0;
    $start_time = isset($_POST["start_time"]) ? $_POST["start_time"] : '';
    $end_time = isset($_POST["end_time"]) ? $_POST["end_time"] : '';

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
            empty($schedule_date) || (!is_numeric($date_span) && $date_span !== 0) ||
            empty($start_time) || empty($end_time)
        ) {
            $errorMessage = "All fields are required!";
            break;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date)) {
            $errorMessage = "Invalid schedule date format.";
            break;
        }

        if ($date_span < 0) {
            $errorMessage = "Date span must be 0 or greater.";
            break;
        }

        $schedule_date_esc = $conn->real_escape_string($schedule_date);
        $start_time_esc = $conn->real_escape_string($start_time);
        $end_time_esc = $conn->real_escape_string($end_time);

        $id = (int)$id;
        $farmer_id = (int)$farmer_id;
        $machine_id = (int)$machine_id;
        $date_span = (int)$date_span;

        $sql = "UPDATE schedules 
                SET farmer_id = $farmer_id, machine_id = $machine_id, schedule_date = '$schedule_date_esc', date_span = $date_span, start_time = '$start_time_esc', end_time = '$end_time_esc' 
                WHERE id = $id";

        $result = $conn->query($sql);

        if (!$result) {
            $errorMessage = "Invalid query: " . $conn->error;
            break;
        }

        $successMessage = "Schedule successfully updated!";
        header("Location: " . $cancelUrl);
        exit;

    } while (false);
}

// compute server-side end date preview
$end_date_preview = "";
if (!empty($schedule_date) && is_numeric($date_span)) {
    $end_date_preview = date('Y-m-d', strtotime($schedule_date . " +{$date_span} days"));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Edit Schedule</title>
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
                <strong>" . htmlspecialchars($errorMessage) . "</strong>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
            ";
        }
        if (!empty($successMessage)) {
            echo "
            <div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>" . htmlspecialchars($successMessage) . "</strong>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
            ";
        }
        ?>

        <form method="post" id="editScheduleForm">
            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($rawRedirect) ?>">

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
                    <input type="date" class="form-control" id="schedule_date" name="schedule_date" value="<?= htmlspecialchars($schedule_date) ?>" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Date Span (days)</label>
                <div class="col-sm-6">
                    <input type="number" class="form-control" id="date_span" name="date_span" value="<?= htmlspecialchars($date_span) ?>" min="0" required>
                    <small class="text-muted">Number of days to add to the schedule date to get the end date.</small>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">End Date (preview)</label>
                <div class="col-sm-6">
                    <input type="date" class="form-control" id="end_date_preview" value="<?= htmlspecialchars($end_date_preview) ?>" readonly>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">Start Time</label>
                <div class="col-sm-6">
                    <input type="time" class="form-control" name="start_time" value="<?= htmlspecialchars($start_time) ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-3 col-form-label">End Time</label>
                <div class="col-sm-6">
                    <input type="time" class="form-control" name="end_time" value="<?= htmlspecialchars($end_time) ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="offset-sm-3 col-sm-3 d-grid">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                <div class="col-sm-3 d-grid">
                    <a class='btn btn-outline-primary' href="<?= htmlspecialchars($cancelUrl) ?>" role='button'>Cancel Editing</a>
                </div>
            </div>
        </form>
    </div>

    <script>
    (function () {
        const scheduleDateInput = document.getElementById('schedule_date');
        const dateSpanInput = document.getElementById('date_span');
        const endDateInput = document.getElementById('end_date_preview');

        function updateEndDate() {
            const sd = scheduleDateInput.value;
            let span = parseInt(dateSpanInput.value, 10);
            if (!sd || isNaN(span)) {
                endDateInput.value = '';
                return;
            }

            const d = new Date(sd);
            d.setDate(d.getDate() + span);
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            endDateInput.value = `${yyyy}-${mm}-${dd}`;
        }

        scheduleDateInput.addEventListener('change', updateEndDate);
        dateSpanInput.addEventListener('input', updateEndDate);
        updateEndDate();
    })();
    </script>

</body>

</html>