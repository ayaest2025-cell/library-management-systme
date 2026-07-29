<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include 'db.php';

$pageTitle = 'Add Book';
$message = '';
$book = ['title' => '', 'isbn' => '', 'author' => '', 'publisher' => '', 'publication_year' => '', 'category' => '', 'quantity' => 1, 'available_copies' => 1, 'description' => ''];
$categoryResult = $conn->query('SELECT name FROM categories ORDER BY name');
$categories = $categoryResult ? $categoryResult->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($book as $field => $value) { if (isset($_POST[$field])) $book[$field] = trim((string) $_POST[$field]); }
    $quantity = filter_var($book['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $available = filter_var($book['available_copies'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $year = $book['publication_year'] === '' ? null : filter_var($book['publication_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1000, 'max_range' => (int) date('Y')]]);

    if ($book['title'] === '' || $book['author'] === '' || $book['category'] === '' || $quantity === false || $available === false || $available > $quantity || ($book['publication_year'] !== '' && $year === false)) {
        $message = 'Enter a title, author, category, valid publication year, and copy counts.';
    } else {
        $coverImage = uploadBookCover($_FILES['cover_image'] ?? [], $message);
        if ($message === '') {
            $isbn = $book['isbn'] === '' ? null : $book['isbn'];
            $publisher = $book['publisher'] === '' ? null : $book['publisher'];
            $description = $book['description'] === '' ? null : $book['description'];
            $status = $available > 0 ? 'Available' : 'Borrowed';
            $stmt = $conn->prepare('INSERT INTO books (title, isbn, author, publisher, publication_year, category, cover_image, quantity, available_copies, description, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssissiiss', $book['title'], $isbn, $book['author'], $publisher, $year, $book['category'], $coverImage, $quantity, $available, $description, $status);

            if ($stmt->execute()) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Book added successfully.'];
                header('Location: books.php');
                exit();
            }

            // Do not leave an uploaded file behind if its database record cannot be saved.
            deleteBookCover($coverImage);
            $message = 'Unable to save the book. Please try again.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Add Book</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="styles.css"></head>
<body><?php include 'includes/nav.php'; ?><main class="container py-4"><section class="card shadow-sm mx-auto" style="max-width:850px"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Add Book</h1><p class="text-muted mb-0">Add a title and its inventory details.</p></div><a href="books.php" class="btn btn-outline-secondary">Back to Books</a></div>
<?php if ($message): ?><div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="row g-3"><div class="col-md-8"><label class="form-label" for="title">Title *</label><input class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($book['title']); ?>"></div><div class="col-md-4"><label class="form-label" for="isbn">ISBN</label><input class="form-control" id="isbn" name="isbn" maxlength="20" value="<?php echo htmlspecialchars($book['isbn']); ?>"></div><div class="col-md-6"><label class="form-label" for="author">Author *</label><input class="form-control" id="author" name="author" required value="<?php echo htmlspecialchars($book['author']); ?>"></div><div class="col-md-6"><label class="form-label" for="publisher">Publisher</label><input class="form-control" id="publisher" name="publisher" value="<?php echo htmlspecialchars($book['publisher']); ?>"></div><div class="col-md-4"><label class="form-label" for="publication_year">Publication Year</label><input class="form-control" id="publication_year" name="publication_year" type="number" min="1000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($book['publication_year']); ?>"></div><div class="col-md-8"><label class="form-label" for="category">Category *</label><input class="form-control" id="category" name="category" list="category-list" required value="<?php echo htmlspecialchars($book['category']); ?>"><datalist id="category-list"><?php foreach ($categories as $category): ?><option value="<?php echo htmlspecialchars($category['name']); ?>"><?php endforeach; ?></datalist></div><div class="col-md-6"><label class="form-label" for="quantity">Quantity *</label><input class="form-control" id="quantity" name="quantity" type="number" min="1" required value="<?php echo htmlspecialchars((string) $book['quantity']); ?>"></div><div class="col-md-6"><label class="form-label" for="available_copies">Available Copies *</label><input class="form-control" id="available_copies" name="available_copies" type="number" min="0" required value="<?php echo htmlspecialchars((string) $book['available_copies']); ?>"></div><div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($book['description']); ?></textarea></div><div class="col-12"><label class="form-label" for="cover_image">Book Cover Image</label><input class="form-control" id="cover_image" name="cover_image" type="file" accept="image/jpeg,image/png,image/gif,image/webp"><div class="form-text">JPG, PNG, GIF, or WEBP; maximum 5 MB.</div></div><div class="col-12"><button class="btn btn-primary" type="submit">Add Book</button></div></form></div></section></main></body></html>
