<?php
$pageTitle = "Books";

$servername = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "library_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($servername, $dbUsername, $dbPassword, $dbName);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $result = $conn->query("SELECT id, title, author, category, created_at FROM books ORDER BY id DESC");
    $books = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $books = [];
    $errorMessage = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f2f2f2;
        }

        .actions a {
            margin-right: 8px;
            text-decoration: none;
            color: #007bff;
        }

        .actions a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header>
        <h1>Library Management System</h1>
        <p>Browse all books in the collection.</p>
    </header>

    <nav>
        <a href="index.php">Home</a>
        <a href="books.php">Books</a>
        <a href="add-book.php">Add Book</a>
    </nav>

    <main>
        <section class="welcome">
            <h2>All Books</h2>

            <?php if (!empty($errorMessage)): ?>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            <?php endif; ?>

            <?php if (!empty($books)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['id']); ?></td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo htmlspecialchars($book['category']); ?></td>
                                <td><?php echo htmlspecialchars($book['created_at']); ?></td>
                                <td class="actions">
                                    <a href="edit-book.php?id=<?php echo $book['id']; ?>">Edit</a>
                                    <a href="delete-book.php?id=<?php echo $book['id']; ?>" onclick="return confirm('Delete this book?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No books found.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
