<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle redirect parameter
$rawRedirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
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

// Get the machine ID from the URL
$machine_id = $_GET['id'] ?? null;

if (!$machine_id) {
    die("No machine ID provided.");
}

// Fetch the machine name
$machineQuery = "SELECT name FROM machines WHERE id = $machine_id";
$machineResult = $conn->query($machineQuery);
$machineRow = $machineResult->fetch_assoc();

if (!$machineRow) {
    die("Machine not found.");
}

$machineName = $machineRow['name'];

// Fetch usage history for the machine
$historyQuery = "
    SELECT 
        schedules.schedule_date,
        schedules.date_span,
        farmers.name AS farmer_name
    FROM schedules
    JOIN farmers ON schedules.farmer_id = farmers.id
    WHERE schedules.machine_id = $machine_id
    ORDER BY schedules.schedule_date DESC
";
$historyResult = $conn->query($historyQuery);

if (!$historyResult) {
    die("Error fetching history: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Machine History | <?= htmlspecialchars($machineName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="container my-5">
        <h2>Usage History for: <?= htmlspecialchars($machineName) ?></h2>
        <a class="btn btn-outline-primary mb-3" href="<?= htmlspecialchars($cancelUrl) ?>" role="button">Back to Machines</a>

        <?php if ($historyResult->num_rows > 0): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Total Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $historyResult->fetch_assoc()): ?>
                        <?php 
                            $startDate = date('Y-m-d', strtotime($row['schedule_date']));
                            $endDate = date('Y-m-d', strtotime($row['schedule_date'] . " +{$row['date_span']} days"));
                            $totalDays = $row['date_span'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row['farmer_name']) ?></td>
                            <td><?= $startDate ?></td>
                            <td><?= $endDate ?></td>
                            <td><?= $totalDays ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No usage history found for this machine.</p>
        <?php endif; ?>
    </div>
</body>

</html>