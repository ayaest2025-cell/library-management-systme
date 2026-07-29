<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = "Books";
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim($_GET['search'] ?? '');

$sql = "SELECT id, title, author, category, cover_image, status, borrower_name, created_at FROM books";
$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE title LIKE ? OR author LIKE ?";
    $like = "%{$search}%";
    $params = [$like, $like];
    $types = "ss";
}

$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
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
        <p>Browse all books in the collection.</p>
    </header>

    <main>
        <section class="welcome">
            <h2>All Books</h2>

            <?php if ($flash): ?>
                <div class="flash <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>

            <form method="get" action="books.php" class="search-form">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by title or author">
                <button type="submit">Search</button>
            </form>

            <?php if (!empty($books)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cover</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Borrower</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['id']); ?></td>
                                <td>
                                    <?php if (!empty($book['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" alt="Cover" class="book-cover-thumb">
                                    <?php else: ?>
                                        <span class="muted-text">No cover</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo htmlspecialchars($book['category']); ?></td>
                                <td><?php echo htmlspecialchars($book['status']); ?></td>
                                <td><?php echo htmlspecialchars($book['borrower_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($book['created_at']); ?></td>
                                <td class="actions">
                                    <a href="edit-book.php?id=<?php echo $book['id']; ?>" class="action-link">Edit</a>
                                    <a href="delete-book.php?id=<?php echo $book['id']; ?>" class="action-link danger" onclick="return confirm('Delete this book?')">Delete</a>
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
