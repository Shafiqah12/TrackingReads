<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "TrackingReads";

$conn = null;

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    error_log("db_connect.php connection error: " . $e->getMessage());
}
// No closing 