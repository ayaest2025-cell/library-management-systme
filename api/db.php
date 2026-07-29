<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    initializeSchema($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) DEFAULT NULL,
        full_name VARCHAR(150) DEFAULT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) DEFAULT NULL,
        password_hash VARCHAR(255) DEFAULT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'borrower',
        first_name VARCHAR(100) DEFAULT NULL,
        last_name VARCHAR(100) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("ALTER TABLE users MODIFY COLUMN email VARCHAR(150) NOT NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        author VARCHAR(150) NOT NULL,
        category VARCHAR(100) NOT NULL,
        available_copies INT NOT NULL DEFAULT 1,
        isbn VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        barcode VARCHAR(100) NOT NULL UNIQUE,
        category_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS borrow_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_id INT NOT NULL,
        issue_date DATETIME DEFAULT NULL,
        due_date DATETIME NOT NULL,
        return_date DATETIME DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'requested',
        fine_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (item_id) REFERENCES items(id)
    )");

    ensureDefaultAdmin($pdo);
}

function ensureDefaultAdmin(PDO $pdo): void
{
    $email = 'admin@gmail.com';
    $fullName = 'System Administrator';
    $plainPassword = 'admin123';
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        $updateStmt = $pdo->prepare('UPDATE users SET full_name = ?, password = ?, password_hash = ?, role = ? WHERE email = ?');
        $updateStmt->execute([$fullName, $hashedPassword, $hashedPassword, 'admin', $email]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO users (name, full_name, email, password, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$fullName, $fullName, $email, $hashedPassword, $hashedPassword, 'admin']);
}
