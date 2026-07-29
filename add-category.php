<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Add Category';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$errorMessage = '';

$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($name === '') {
        $errorMessage = 'Category name is required.';
    } elseif (strlen($name) > 100) {
        $errorMessage = 'Category name must be 100 characters or less.';
    } elseif (strlen($description) > 255) {
        $errorMessage = 'Description must be 255 characters or less.';
    } else {
        $checkStmt = $conn->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $checkStmt->bind_param('s', $name);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $errorMessage = 'A category with that name already exists.';
        } else {
            $insertStmt = $conn->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $insertStmt->bind_param('ss', $name, $description);
            $insertStmt->execute();

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category added successfully.'];
            header('Location: categories.php');
            exit();
        }
    }
}
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
                <h2 class="mb-3">Add Category</h2>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger'); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <form method="post" action="add-category.php" class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Category</button>
                        <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
