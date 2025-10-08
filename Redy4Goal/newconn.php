<?php
// Database credentials
$servername = "localhost";  // XAMPP server default
$username = "root";         // default XAMPP MySQL username
$password = "";             // default XAMPP MySQL password is empty
$dbname = "ready3goal"; // your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo ""; // Uncomment for testing
?>
