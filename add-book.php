<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = "Add Book";
$message = '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "library_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $dbUsername, $dbPassword);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
    $conn->select_db($dbName);

    $conn->query("CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        author VARCHAR(100) NOT NULL,
        category VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');

        if ($title !== '' && $author !== '' && $category !== '') {
            $stmt = $conn->prepare("INSERT INTO books (title, author, category) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $author, $category);
            $stmt->execute();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Book added successfully.'];
            header('Location: books.php');
            exit();
        } else {
            $message = "Please fill in all fields.";
        }
    }
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <header>
        <h1>Library Management System</h1>
        <p>Add a new book to the library database.</p>
    </header>

    <main>
        <section class="welcome">
            <h2>Add New Book</h2>

            <?php if ($message !== ''): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="flash <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <form method="post" action="add-book.php">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" required>

                <label for="author">Author:</label>
                <input type="text" id="author" name="author" required>

                <label for="category">Category:</label>
                <input type="text" id="category" name="category" required>

                <button type="submit">Add Book</button>
            </form>
        </section>
    </main>
</body>
</html>
