<?php
$servername = "localhost";
$username = "u846163676_ttjgen";      // Default XAMPP/WAMP username
$password = "TTJnextgen@2025";        // Default XAMPP/WAMP password (leave empty)
$dbname = "u846163676_ttj";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>