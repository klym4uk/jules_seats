<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Replace with your DB username if different for XAMPP
define('DB_PASSWORD', '');   // Replace with your DB password if different for XAMPP
define('DB_NAME', 'seats_db');

/* Attempt to connect to MySQL database */
try {
    $pdo = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e){
    die("ERROR: Could not connect. " . $e->getMessage());
}
?>
