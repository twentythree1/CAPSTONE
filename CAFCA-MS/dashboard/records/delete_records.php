<?php 
if (isset($_GET["id"])) {
    $id = $_GET["id"];

    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "testdb";

    $conn = new mysqli($servername, $username, $password, $database);

    $sql = "DELETE FROM records WHERE id=$id";
    $conn->query($sql);
}

header("location: /CAFCA-MS/dashboard/records/records.php");
exit;
?>