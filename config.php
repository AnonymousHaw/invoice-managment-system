<?php
// config.php
$host = 'localhost';
$dbname = 'invoice_system';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch(Exception $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?>