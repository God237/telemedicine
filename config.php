<?php
// Database configuration
$host = 'localhost';
$dbname = 'telemedicine'; // Make sure this database exists
$username = 'root';
$password = ''; // XAMPP default is empty string, not 'root'

try {
    // Create connection
    $conn = new mysqli($host, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to UTF-8
    $conn->set_charset("utf8");
    
    // For debugging - remove in production
    // echo "Connected successfully";
    
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>