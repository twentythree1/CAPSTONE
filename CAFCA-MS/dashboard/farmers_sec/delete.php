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

    $conn->query("DELETE FROM records WHERE farmer_id = $id");
    $conn->query("DELETE FROM schedules WHERE farmer_id = $id");
    $conn->query("DELETE FROM farmers WHERE id = $id");
    $conn->close();
}

header("location: /CAPSTONE/CAFCA-MS/dashboard/farmers_sec/farmers.php");
exit;
?>
