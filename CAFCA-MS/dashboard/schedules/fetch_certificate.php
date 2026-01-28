<?php
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    echo "<h3>Certificate for Schedule #$id</h3>";
    echo "<p>Farmer Name: John Doe</p>";
    echo "<p>Machine Used: Tractor X200</p>";
    echo "<p>Date: 2025-05-05</p>";
    echo "<p>Status: Completed</p>";
} else {
    echo "<p>Invalid request.</p>";
}
?>
