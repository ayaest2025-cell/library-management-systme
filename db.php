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
ensureCategoriesTable($conn);
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
        isbn VARCHAR(20) DEFAULT NULL,
        author VARCHAR(150) NOT NULL,
        publisher VARCHAR(150) DEFAULT NULL,
        publication_year SMALLINT DEFAULT NULL,
        category VARCHAR(100) NOT NULL,
        cover_image VARCHAR(255) DEFAULT NULL,
        quantity INT UNSIGNED NOT NULL DEFAULT 1,
        available_copies INT UNSIGNED NOT NULL DEFAULT 1,
        description TEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Available',
        borrower_name VARCHAR(150) DEFAULT NULL,
        borrowed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = [
        'isbn' => 'VARCHAR(20) DEFAULT NULL',
        'publisher' => 'VARCHAR(150) DEFAULT NULL',
        'publication_year' => 'SMALLINT DEFAULT NULL',
        'cover_image' => 'VARCHAR(255) DEFAULT NULL',
        'quantity' => 'INT UNSIGNED NOT NULL DEFAULT 1',
        'available_copies' => 'INT UNSIGNED NOT NULL DEFAULT 1',
        'description' => 'TEXT DEFAULT NULL',
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'Available'",
        'borrower_name' => 'VARCHAR(150) DEFAULT NULL',
        'borrowed_at' => 'TIMESTAMP NULL DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!columnExists($conn, 'books', $column)) {
            $conn->query("ALTER TABLE books ADD COLUMN $column $definition");
        }
    }

    // Bring records created before copy tracking into the new model.
    $conn->query("UPDATE books SET quantity = 1 WHERE quantity IS NULL OR quantity < 1");
    $conn->query("UPDATE books SET available_copies = CASE WHEN status = 'Borrowed' THEN 0 ELSE quantity END WHERE available_copies IS NULL OR available_copies > quantity OR (status = 'Borrowed' AND available_copies = quantity)");
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

function ensureCategoriesTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = [
        'name' => 'VARCHAR(100) NOT NULL UNIQUE',
        'description' => 'VARCHAR(255) DEFAULT NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    ];

    foreach ($columns as $column => $definition) {
        if (!columnExists($conn, 'categories', $column)) {
            $conn->query("ALTER TABLE categories ADD COLUMN $column $definition");
        }
    }

    seedCategoriesFromBooks($conn);
}

function seedCategoriesFromBooks(mysqli $conn): void
{
    $result = $conn->query("SELECT DISTINCT TRIM(category) AS category_name FROM books WHERE TRIM(category) <> ''");
    if (!$result) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $name = trim((string) $row['category_name']);
        if ($name === '') {
            continue;
        }

        $check = $conn->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $check->bind_param('s', $name);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows === 0) {
            $insert = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
            $insert->bind_param('s', $name);
            $insert->execute();
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
        // Existing administrators retain their own credentials.
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

function uploadBookCover(array $file, ?string &$error): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        $error = 'Upload a valid cover image smaller than 5 MB.';
        return null;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if ($imageInfo === false || !isset($allowedTypes[$imageInfo[2]])) {
        $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        $error = 'Unable to create the cover upload folder.';
        return null;
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$imageInfo[2]];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $fileName)) {
        $error = 'Unable to upload the cover image.';
        return null;
    }

    return 'uploads/' . $fileName;
}

/** Remove a cover only when it is an application-managed upload. */
function deleteBookCover(?string $coverImage): void
{
    if (!$coverImage || !preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|gif|webp)$#', $coverImage)) {
        return;
    }

    $path = __DIR__ . '/' . $coverImage;
    if (is_file($path)) {
        unlink($path);
    }
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
    $select = ['id', 'email', 'role'];

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

/** Return a per-session CSRF token for state-changing forms. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
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
