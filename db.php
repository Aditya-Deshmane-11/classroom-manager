<?php
$servername = "localhost";   // Local server
$username   = "root";        // Default local username
$password   = "";            // Default local password (empty)
$database   = "mywebsite";   // Change to your local DB name

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>

