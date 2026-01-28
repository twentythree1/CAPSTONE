<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "testdb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = $_GET['id'];

$conn->query("UPDATE schedules SET status='Approved' WHERE id=$id");

header("Location: schedule.php?status=Pending&approved=1");
exit();
?>
