<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'library_db';

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS `{$dbname}`");
$conn->select_db($dbname);

$conn->query("CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    author VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Available',
    borrower_name VARCHAR(150) DEFAULT NULL,
    borrowed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$columns = ['cover_image' => 'VARCHAR(255) DEFAULT NULL', 'status' => "VARCHAR(20) NOT NULL DEFAULT 'Available'", 'borrower_name' => 'VARCHAR(150) DEFAULT NULL', 'borrowed_at' => 'TIMESTAMP NULL DEFAULT NULL'];
foreach ($columns as $column => $definition) {
    $check = $conn->query("SHOW COLUMNS FROM books LIKE '$column'");
    if ($check->num_rows === 0) {
        $conn->query("ALTER TABLE books ADD COLUMN $column $definition");
    }
}
?>
