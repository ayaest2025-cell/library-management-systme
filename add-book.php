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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = trim($_POST['status'] ?? 'Available');
    $borrowerName = trim($_POST['borrower_name'] ?? '');

    if ($title !== '' && $author !== '' && $category !== '') {
        if ($status === 'Borrowed' && $borrowerName === '') {
            $message = 'Borrower details are required when the book is marked as borrowed.';
        } else {
            $coverImage = null;

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
                $borrowedAt = $status === 'Borrowed' ? date('Y-m-d H:i:s') : null;
                if ($status === 'Available') {
                    $borrowerName = '';
                }

                $stmt = $conn->prepare('INSERT INTO books (title, author, category, cover_image, status, borrower_name, borrowed_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssss', $title, $author, $category, $coverImage, $status, $borrowerName, $borrowedAt);
                $stmt->execute();

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Book added successfully.'];
                header('Location: books.php');
                exit();
            }
        }
    } else {
        $message = 'Please fill in all fields.';
    }
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
        <section class="welcome form-card">
            <h2>Add New Book</h2>

            <?php if ($message !== ''): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="flash <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <form method="post" action="add-book.php" enctype="multipart/form-data" class="book-form">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" required>

                <label for="author">Author:</label>
                <input type="text" id="author" name="author" required>

                <label for="category">Category:</label>
                <input type="text" id="category" name="category" required>

                <label for="cover_image">Cover Image:</label>
                <input type="file" id="cover_image" name="cover_image" accept="image/*">

                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="Available">Available</option>
                    <option value="Borrowed">Borrowed</option>
                </select>

                <label for="borrower_name">Borrower Name:</label>
                <input type="text" id="borrower_name" name="borrower_name" placeholder="Required when borrowed">

                <button type="submit">Add Book</button>
            </form>
        </section>
    </main>
</body>
</html>
