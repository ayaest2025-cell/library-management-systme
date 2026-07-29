<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Member Details';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$member = null;

if ($id > 0) {
    $stmt = $conn->prepare('SELECT id, member_code, full_name, email, phone, address, status, joined_at FROM members WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
}

if ($member === null) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Member not found.'];
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
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger'); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="mb-1">Member Details</h2>
                        <p class="text-muted mb-0">Detailed profile for <?php echo htmlspecialchars($member['full_name']); ?></p>
                    </div>
                    <div class="btn-group">
                        <a href="edit-member.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-primary">Edit</a>
                        <a href="delete-member.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-outline-danger">Delete</a>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <p class="mb-1 text-muted">Member Code</p>
                            <h5><?php echo htmlspecialchars($member['member_code']); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <p class="mb-1 text-muted">Status</p>
                            <h5><span class="badge bg-<?php echo $member['status'] === 'Active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($member['status']); ?></span></h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <p class="mb-1 text-muted">Full Name</p>
                            <h5><?php echo htmlspecialchars($member['full_name']); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <p class="mb-1 text-muted">Email</p>
                            <h5><?php echo htmlspecialchars($member['email'] ?? '—'); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <p class="mb-1 text-muted">Phone</p>
                            <h5><?php echo htmlspecialchars($member['phone'] ?? '—'); ?></h5>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-light">
                            <p class="mb-1 text-muted">Joined At</p>
                            <h5><?php echo htmlspecialchars($member['joined_at']); ?></h5>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 border rounded bg-light">
                            <p class="mb-1 text-muted">Address</p>
                            <h5><?php echo nl2br(htmlspecialchars($member['address'] ?? '—')); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
