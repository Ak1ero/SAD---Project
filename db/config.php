<?php

date_default_timezone_set('Asia/Manila');

$conn = new mysqli("localhost", "root", "", "event");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>