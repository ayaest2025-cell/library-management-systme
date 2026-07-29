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
    $stmt = $conn->prepare('SELECT id, title, author, category, cover_image, status, borrower_name FROM books WHERE id = ?');
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
    $status = trim($_POST['status'] ?? 'Available');
    $borrowerName = trim($_POST['borrower_name'] ?? '');

    if ($id > 0 && $title !== '' && $author !== '' && $category !== '') {
        if ($status === 'Borrowed' && $borrowerName === '') {
            $message = 'Borrower details are required when the book is marked as borrowed.';
        } else {
            $stmt = $conn->prepare('SELECT cover_image FROM books WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $currentBook = $stmt->get_result()->fetch_assoc();
            $coverImage = $currentBook['cover_image'] ?? null;

            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $extension = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));

                if (!in_array($extension, $allowedExtensions, true)) {
                    $message = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
                } else {
                    $fileName = uniqid('cover_', true) . '.' . $extension;
                    $targetPath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                        $coverImage = 'uploads/' . $fileName;
                    } else {
                        $message = 'Unable to upload the cover image.';
                    }
                }
            }

            if ($message === '') {
                if ($status === 'Available') {
                    $borrowerName = '';
                    $borrowedAt = null;
                } else {
                    $borrowedAt = date('Y-m-d H:i:s');
                }

                $stmt = $conn->prepare('UPDATE books SET title = ?, author = ?, category = ?, cover_image = ?, status = ?, borrower_name = ?, borrowed_at = ? WHERE id = ?');
                $stmt->bind_param('sssssssi', $title, $author, $category, $coverImage, $status, $borrowerName, $borrowedAt, $id);
                $stmt->execute();

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Book updated successfully.'];
                header('Location: books.php');
                exit();
            }
        }
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
        <section class="welcome form-card">
            <h2>Edit Book</h2>
            <?php if ($message !== ''): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <form method="post" action="edit-book.php" enctype="multipart/form-data" class="book-form">
                <input type="hidden" name="id" value="<?php echo (int) $book['id']; ?>">

                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required>

                <label for="author">Author</label>
                <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required>

                <label for="category">Category</label>
                <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($book['category']); ?>" required>

                <label for="cover_image">Cover Image</label>
                <input type="file" id="cover_image" name="cover_image" accept="image/*">

                <?php if (!empty($book['cover_image'])): ?>
                    <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Current cover" class="cover-preview">
                <?php endif; ?>

                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="Available" <?php echo ($book['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                    <option value="Borrowed" <?php echo ($book['status'] === 'Borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                </select>

                <label for="borrower_name">Borrower Name</label>
                <input type="text" id="borrower_name" name="borrower_name" value="<?php echo htmlspecialchars($book['borrower_name'] ?? ''); ?>" placeholder="Required when borrowed">

                <button type="submit">Save Changes</button>
            </form>
        </section>
    </main>
</body>
</html>
