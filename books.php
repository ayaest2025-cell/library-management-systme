<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim($_GET['search'] ?? '');
$selectedCategory = trim($_GET['category'] ?? '');

$categoriesResult = $conn->query("SELECT DISTINCT category FROM books WHERE TRIM(category) <> '' ORDER BY category");
$categories = $categoriesResult ? $categoriesResult->fetch_all(MYSQLI_ASSOC) : [];

$sql = 'SELECT id, title, isbn, author, publisher, publication_year, category, cover_image, quantity, available_copies, description FROM books';
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(title LIKE ? OR isbn LIKE ? OR author LIKE ? OR publisher LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($selectedCategory !== '') {
    $where[] = 'category = ?';
    $params[] = $selectedCategory;
    $types .= 's';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY id DESC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Books</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="container py-4">
        <section class="card shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                    <div>
                        <h1 class="h3 mb-1"><i class="bi bi-book"></i> Books</h1>
                        <p class="text-muted mb-0">Manage titles, cover images, and available inventory.</p>
                    </div>
                    <a href="add-book.php" class="btn btn-primary align-self-md-center">
                        <i class="bi bi-plus-circle"></i> Add Book
                    </a>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                        <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form class="row g-2 mb-4" method="get">
                    <div class="col-md-6">
                        <input class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search title, ISBN, author, or publisher">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['category']); ?>" <?php echo $selectedCategory === $category['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
                    </div>
                </form>

                <?php if ($books): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Cover</th>
                                    <th>Book</th>
                                    <th>Category</th>
                                    <th>Published</th>
                                    <th>Copies</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($books as $book): ?>
                                    <tr>
                                        <td>
                                            <?php if ($book['cover_image']): ?>
                                                <img src="<?php echo htmlspecialchars($book['cover_image']); ?>" class="book-cover-thumb" alt="Cover of <?php echo htmlspecialchars($book['title']); ?>">
                                            <?php else: ?>
                                                <span class="text-muted small"><i class="bi bi-image"></i> No cover</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                            <div class="small text-muted">
                                                <?php echo htmlspecialchars($book['author']); ?>
                                                <?php if ($book['isbn']): ?> &middot; ISBN <?php echo htmlspecialchars($book['isbn']); ?><?php endif; ?>
                                                <?php if ($book['publisher']): ?><br><?php echo htmlspecialchars($book['publisher']); ?><?php endif; ?>
                                            </div>
                                            <?php if ($book['description']): ?>
                                                <div class="small text-muted mt-1"><?php echo htmlspecialchars($book['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($book['category']); ?></span></td>
                                        <td><?php echo $book['publication_year'] ? (int) $book['publication_year'] : '&mdash;'; ?></td>
                                        <td>
                                            <span class="badge text-bg-<?php echo $book['available_copies'] > 0 ? 'success' : 'secondary'; ?>">
                                                <?php echo (int) $book['available_copies']; ?> / <?php echo (int) $book['quantity']; ?> available
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 flex-wrap">
                                                <a class="btn btn-outline-primary btn-sm" href="edit-book.php?id=<?php echo (int) $book['id']; ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <?php if ($book['available_copies'] > 0): ?>
                                                    <a class="btn btn-outline-success btn-sm" href="borrow.php?book_id=<?php echo (int) $book['id']; ?>">
                                                        <i class="bi bi-journal-plus"></i> Borrow
                                                    </a>
                                                <?php endif; ?>
                                                <form method="post" action="delete-book.php" class="d-inline" onsubmit="return confirm('Delete this book?');">
                                                    <input type="hidden" name="id" value="<?php echo (int) $book['id']; ?>">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-light text-center mb-0">
                        <i class="bi bi-inbox me-2"></i>No books found. 
                        <a href="add-book.php" class="link-primary">Add your first book</a>.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

