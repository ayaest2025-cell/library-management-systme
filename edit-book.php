<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Edit Book';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $conn->prepare('SELECT id, title, author, category FROM books WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();

    if (!$book) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Book not found.'];
        header('Location: books.php');
        exit();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if ($id > 0 && $title !== '' && $author !== '' && $category !== '') {
        $stmt = $conn->prepare('UPDATE books SET title = ?, author = ?, category = ? WHERE id = ?');
        $stmt->bind_param('sssi', $title, $author, $category, $id);
        $stmt->execute();

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Book updated successfully.'];
        header('Location: books.php');
        exit();
    } else {
        $message = 'Please fill in all fields.';
    }
} else {
    header('Location: books.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <main>
        <section class="welcome">
            <h2>Edit Book</h2>
            <?php if ($message !== ''): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <form method="post" action="edit-book.php" class="book-form">
                <input type="hidden" name="id" value="<?php echo (int) $book['id']; ?>">

                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>

                <label for="author">Author</label>
                <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>

                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($book['category']); ?>" required>

                <button type="submit">Save Changes</button>
            </form>
        </section>
    </main>
</body>
</html>
