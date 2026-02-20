<?php 
if (isset($_GET["id"])) {
    $id = $_GET["id"];

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

    $conn->query("DELETE FROM machine_history WHERE machine_id = $id");
    $conn->query("DELETE FROM schedules WHERE machine_id = $id");
    $conn->query("DELETE FROM machines WHERE id = $id");
    $conn->close();

    header("Location: " . $cancelUrl);
    exit;
}

header("location: /CAPSTONE/CAFCA-MS/dashboard/machines/machine.php");
exit;
?>