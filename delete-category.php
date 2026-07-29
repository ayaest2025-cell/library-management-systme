<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Delete Category';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$category = null;

if ($id > 0) {
    $stmt = $conn->prepare('SELECT id, name FROM categories WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
}

if ($category === null) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Category not found.'];
    header('Location: categories.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($deleteId > 0) {
        $deleteStmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
        $deleteStmt->bind_param('i', $deleteId);
        $deleteStmt->execute();

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category deleted successfully.'];
    }

    header('Location: categories.php');
    exit();
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
                <h2 class="mb-3">Delete Category</h2>
                <p class="text-muted">Are you sure you want to remove <strong><?php echo htmlspecialchars($category['name']); ?></strong>?</p>

                <form method="post" action="delete-category.php?id=<?php echo (int) $category['id']; ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">Delete</button>
                        <a href="categories.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
