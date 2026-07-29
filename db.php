<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'library_management_system';

$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS `{$dbname}`");
$conn->select_db($dbname);

ensureUsersTable($conn);
ensureBooksTable($conn);
ensureMembersTable($conn);
ensureDefaultAdmin($conn);

function ensureUsersTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS users (
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

    $columns = [
        'name' => "VARCHAR(150) DEFAULT NULL",
        'full_name' => "VARCHAR(150) DEFAULT NULL",
        'password' => "VARCHAR(255) DEFAULT NULL",
        'password_hash' => "VARCHAR(255) DEFAULT NULL",
        'role' => "VARCHAR(20) NOT NULL DEFAULT 'borrower'",
        'first_name' => "VARCHAR(100) DEFAULT NULL",
        'last_name' => "VARCHAR(100) DEFAULT NULL",
        'phone' => "VARCHAR(30) DEFAULT NULL",
        'address' => "VARCHAR(255) DEFAULT NULL",
        'profile_image' => "VARCHAR(255) DEFAULT NULL",
    ];

    foreach ($columns as $column => $definition) {
        if (!columnExists($conn, 'users', $column)) {
            $conn->query("ALTER TABLE users ADD COLUMN $column $definition");
        }
    }

    $conn->query("ALTER TABLE users MODIFY COLUMN email VARCHAR(150) NOT NULL");
}

function ensureBooksTable(mysqli $conn): void
{
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

    $columns = [
        'cover_image' => 'VARCHAR(255) DEFAULT NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'Available'",
        'borrower_name' => 'VARCHAR(150) DEFAULT NULL',
        'borrowed_at' => 'TIMESTAMP NULL DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!columnExists($conn, 'books', $column)) {
            $conn->query("ALTER TABLE books ADD COLUMN $column $definition");
        }
    }
}

function ensureMembersTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_code VARCHAR(50) NOT NULL UNIQUE,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        address VARCHAR(255) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = [
        'member_code' => "VARCHAR(50) NOT NULL UNIQUE",
        'full_name' => 'VARCHAR(150) NOT NULL',
        'email' => 'VARCHAR(150) DEFAULT NULL',
        'phone' => 'VARCHAR(30) DEFAULT NULL',
        'address' => 'VARCHAR(255) DEFAULT NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'Active'",
        'joined_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($columns as $column => $definition) {
        if (!columnExists($conn, 'members', $column)) {
            $conn->query("ALTER TABLE members ADD COLUMN $column $definition");
        }
    }
}

function ensureDefaultAdmin(mysqli $conn): void
{
    $email = 'admin@gmail.com';
    $fullName = 'System Administrator';
    $plainPassword = 'admin123';
    $role = 'admin';
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $updateStmt = $conn->prepare('UPDATE users SET full_name = ?, password = ?, password_hash = ?, role = ? WHERE id = ?');
        $updateStmt->bind_param('ssssi', $fullName, $hashedPassword, $hashedPassword, $role, $user['id']);
        $updateStmt->execute();
        return;
    }

    $insertStmt = $conn->prepare('INSERT INTO users (name, full_name, email, password, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
    $insertStmt->bind_param('ssssss', $fullName, $fullName, $email, $hashedPassword, $hashedPassword, $role);
    $insertStmt->execute();
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

    return $result && $result->num_rows > 0;
}

function getTableColumns(mysqli $conn, string $table): array
{
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    $columns = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

function getUserByEmail(mysqli $conn, string $email): ?array
{
    $columns = getTableColumns($conn, 'users');
    $select = ['id', 'email'];

    if (in_array('name', $columns, true)) {
        $select[] = 'name';
    }

    if (in_array('full_name', $columns, true)) {
        $select[] = 'full_name';
    }

    if (in_array('password', $columns, true)) {
        $select[] = 'password';
    }

    if (in_array('password_hash', $columns, true)) {
        $select[] = 'password_hash';
    }

    $stmt = $conn->prepare('SELECT ' . implode(', ', $select) . ' FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows === 1 ? $result->fetch_assoc() : null;
}

function getUserProfileData(mysqli $conn, int $userId): ?array
{
    $columns = getTableColumns($conn, 'users');
    $select = ['id', 'email'];

    if (in_array('name', $columns, true)) {
        $select[] = 'name';
    }

    if (in_array('full_name', $columns, true)) {
        $select[] = 'full_name';
    }

    if (in_array('role', $columns, true)) {
        $select[] = 'role';
    }

    foreach (['first_name', 'last_name', 'phone', 'address', 'profile_image'] as $field) {
        if (in_array($field, $columns, true)) {
            $select[] = $field;
        }
    }

    $stmt = $conn->prepare('SELECT ' . implode(', ', $select) . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows === 1 ? $result->fetch_assoc() : null;
}

function updateUserProfileData(mysqli $conn, int $userId, array $profileData): bool
{
    $columns = getTableColumns($conn, 'users');
    $assignments = [];
    $values = [];

    foreach (['first_name', 'last_name', 'phone', 'address', 'profile_image'] as $field) {
        if (array_key_exists($field, $profileData) && in_array($field, $columns, true)) {
            $assignments[] = "$field = ?";
            $values[] = trim((string) $profileData[$field]);
        }
    }

    if ($assignments === []) {
        return false;
    }

    $values[] = $userId;

    $stmt = $conn->prepare('UPDATE users SET ' . implode(', ', $assignments) . ' WHERE id = ?');
    $types = str_repeat('s', count($assignments)) . 'i';
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    return true;
}
?>
