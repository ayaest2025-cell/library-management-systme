<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Categories';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim($_GET['search'] ?? '');

$sql = 'SELECT c.id, c.name, c.description, c.created_at, COUNT(b.id) AS total_books FROM categories c LEFT JOIN books b ON LOWER(TRIM(b.category)) = LOWER(TRIM(c.name)) GROUP BY c.id, c.name, c.description, c.created_at';
$params = [];
$types = '';

if ($search !== '') {
    $sql .= ' HAVING c.name LIKE ? OR c.description LIKE ?';
    $like = "%{$search}%";
    $params = [$like, $like];
    $types = 'ss';
}

$sql .= ' ORDER BY c.name ASC';

$stmt = $conn->prepare($sql);
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <main class="container py-4">
        <section class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h2 class="mb-1">Categories</h2>
                        <p class="text-muted mb-0">Manage library categories and see how many books belong to each one.</p>
                    </div>
                    <a href="add-category.php" class="btn btn-primary">Add Category</a>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger'); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <form method="get" action="categories.php" class="row g-2 mb-4">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search categories">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <?php if (!empty($categories)): ?>
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Total Books</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td><?php echo htmlspecialchars($category['description'] ?? '—'); ?></td>
                                        <td><?php echo (int) $category['total_books']; ?></td>
                                        <td><?php echo htmlspecialchars($category['created_at']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit-category.php?id=<?php echo (int) $category['id']; ?>" class="btn btn-outline-primary">Edit</a>
                                                <a href="delete-category.php?id=<?php echo (int) $category['id']; ?>" class="btn btn-outline-danger">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-light text-center">No categories found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
