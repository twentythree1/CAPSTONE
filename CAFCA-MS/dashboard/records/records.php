<?php

session_start();
if (!isset($_SESSION['username'])) {
    header("Location: /CAFCA-MS/login/logindex.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$counts = [
    'Pending' => 0,
    'Approved' => 0,
    'On going' => 0,
    'Completed' => 0
];

$now = new DateTime();

$countSql = "SELECT schedule_date, start_time, end_time, date_span, status FROM schedules";

$countResult = $conn->query($countSql);
if ($countResult) {
    while ($r = $countResult->fetch_assoc()) {
        $dbStatus = $r['status'];
        $scheduleDate = $r['schedule_date'];
        $startTime = $r['start_time'] ?: '00:00:00';
        $endTime = $r['end_time'] ?: '23:59:59';
        $dateSpan = isset($r['date_span']) ? (int)$r['date_span'] : 0;

        try {
            $startDt = new DateTime($scheduleDate . ' ' . $startTime);
        } catch (Exception $e) {
            $startDt = new DateTime($scheduleDate . ' 00:00:00');
        }

        $endDateStr = date('Y-m-d', strtotime($scheduleDate . " +{$dateSpan} days"));
        try {
            $endDt = new DateTime($endDateStr . ' ' . $endTime);
        } catch (Exception $e) {
            $endDt = new DateTime($endDateStr . ' 23:59:59');
        }

        $computedStatus = $dbStatus;
        if ($dbStatus === 'Approved') {
            if ($now >= $startDt && $now <= $endDt) {
                $computedStatus = 'On going';
            } elseif ($now > $endDt) {
                $computedStatus = 'Completed';
            }
        }

        if (!isset($counts[$computedStatus])) $counts[$computedStatus] = 0;
        $counts[$computedStatus]++;
    }
    $countResult->free();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../LandingPage/others/logo.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAFCA | Admin</title>
    <link rel="stylesheet" href="../farmers_sec/farmerstyle.css">
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp">
</head>

<body>
    <div class="container">
        <aside>
            <div class="top">
                <a class="logo" href="../main/dashdex.php">
                    <img src="../../LandingPage/others/logo.png">
                    <h2>CAFCA <span>MS</span></h2>
                </a>
                <div class="close" id="close-btn">
                    <span class="material-icons-sharp">close</span>
                </div>
            </div>

            <div class="sidebar">
                <a href="../main/dashdex.php">
                    <span class="material-icons-sharp">dashboard</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="../farmers_sec/farmers.php">
                    <span class="material-icons-sharp">people</span>
                    <h3>Farmers</h3>
                </a>
                <a href="../machines/machine.php">
                    <span class="material-icons-sharp">agriculture</span>
                    <h3>Machines</h3>
                </a>
                <?php $schedulesStatus = $_GET['status'] ?? ''; ?>
                <div class="sidebar-dropdown <?= $schedulesStatus ? 'open' : '' ?>">
                    <a href="javascript:void(0)" class="dropdown-toggle"
                        aria-expanded="<?= $schedulesStatus ? 'true' : 'false' ?>">
                        <span class="material-icons-sharp">event</span>
                        <h3>Schedules</h3>
                        <span class="material-icons-sharp dropdown-icon">expand_more</span>
                    </a>
                    <div class="dropdown-menu">
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Pending">
                            <span>Pending</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Pending'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Approved">
                            <span>Approved</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Approved'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=On going">
                            <span>On going</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['On going'] ?? 0) ?></span>
                        </a>
                        <a href="/CAPSTONE/CAFCA-MS/dashboard/schedules/schedule.php?status=Completed">
                            <span>Completed</span>
                            <span class="count-badge"><?= htmlspecialchars($counts['Completed'] ?? 0) ?></span>
                        </a>
                    </div>
                </div>
                <a href="../records/records.php" class="active">
                    <span class="material-icons-sharp">topic</span>
                    <h3>Records</h3>
                </a>
                <div class="logout"><a href="../../login/logout.php" onclick="return confirm('Are you sure you want to log out?');" class="danger">
                        <span class="material-icons-sharp">logout</span>
                        <h3>Log out</h3>
                    </a>
                </div>
            </div>
        </aside>

        <main>
            <div class="top">
                <button id="menu-btn">
                    <span class="material-icons-sharp">menu</span>
                </button>
                <div class="theme-toggler">
                    <span class="material-icons-sharp active">light_mode</span>
                    <span class="material-icons-sharp">dark_mode</span>
                </div>
                <div class="profile" style="display: flex; flex-direction: column; text-align: right;">
                    <span
                        style="font-size: 18px; text-transform: capitalize; font-weight: 700; "><?= $_SESSION['username']; ?></span>
                    <small class="text-muted">Admin</small>
                </div>
            </div>
            <div class="title">
                <h2 class="machine-count">List of Records</h2>
                <a href="add_records.php" class="btn btn-primary records" role="button">Add Record</a>
            </div>
            <br>
            <div class='table-scroll'>
                <table style='width:100%' class='table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Farmer</th>
                            <th>Machine</th>
                            <th>Harvest Start Date</th>
                            <th>Harvest End Date</th>
                            <th>Number of Sacks</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "
                        SELECT 
                            records.id AS id,
                            farmers.name AS farmer_name,
                            machines.name AS machine_name,
                            records.harvest_start_date,
                            records.harvest_end_date,
                            records.number_of_sacks
                        FROM records
                        JOIN farmers ON records.farmer_id = farmers.id
                        JOIN machines ON records.machine_id = machines.id
                        ORDER BY records.id DESC
                    ";
                    $result = $conn->query($sql);

                    if (!$result) {
                        die("Invalid query: " . $conn->error);
                    }

                    while ($row = $result->fetch_assoc()) {
                        echo "
                    <tr>
                        <td>" . htmlspecialchars($row['id']) . "</td>
                        <td>" . htmlspecialchars($row['farmer_name']) . "</td>
                        <td>" . htmlspecialchars($row['machine_name']) . "</td>
                        <td>" . htmlspecialchars($row['harvest_start_date']) . "</td>
                        <td>" . htmlspecialchars($row['harvest_end_date']) . "</td>
                        <td>" . htmlspecialchars($row['number_of_sacks']) . "</td>
                        <td>
                            <a class='btn btn-primary btn-sm' href='edit_records.php?id=" . htmlspecialchars($row['id']) . "'>Edit</a>
                            <a class='btn btn-danger btn-sm' 
                                            onclick=\"return confirm('Are you sure you want to delete " . htmlspecialchars($row['farmer_name']) . "\\'s record?');\" 
                                            href='delete_records.php?id=" . htmlspecialchars($row['id']) . "'>Delete</a>
                        </td> 
                    </tr>
                    ";
                    }

                    ?>

                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="../main/dashscript.js"></script>
</body>

</html>
<?php
$conn->close();
?>