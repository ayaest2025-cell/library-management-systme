<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Delete Member';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$member = null;

if ($id > 0) {
    $stmt = $conn->prepare('SELECT id, member_code, full_name FROM members WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
}

if ($member === null) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Member not found.'];
    header('Location: members.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($deleteId > 0) {
        $deleteStmt = $conn->prepare('DELETE FROM members WHERE id = ?');
        $deleteStmt->bind_param('i', $deleteId);
        $deleteStmt->execute();

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Member deleted successfully.'];
    }

    header('Location: members.php');
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
                <h2 class="mb-3">Delete Member</h2>
                <p class="text-muted">Are you sure you want to remove <strong><?php echo htmlspecialchars($member['full_name']); ?></strong> from the library members list?</p>

                <form method="post" action="delete-member.php?id=<?php echo (int) $member['id']; ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $member['id']; ?>">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">Delete</button>
                        <a href="members.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
