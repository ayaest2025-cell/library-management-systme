<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Members';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim($_GET['search'] ?? '');

$sql = 'SELECT id, member_code, full_name, email, phone, address, status, joined_at FROM members';
$params = [];
$types = '';

if ($search !== '') {
    $sql .= ' WHERE member_code LIKE ? OR full_name LIKE ? OR email LIKE ? OR phone LIKE ?';
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
}

$sql .= ' ORDER BY id DESC';

$stmt = $conn->prepare($sql);
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$members = $result->fetch_all(MYSQLI_ASSOC);
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
                        <h2 class="mb-1">Members Management</h2>
                        <p class="text-muted mb-0">Search, view, edit, and remove library members.</p>
                    </div>
                    <a href="add-member.php" class="btn btn-primary">Add Member</a>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger'); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <form method="get" action="members.php" class="row g-2 mb-4">
                    <div class="col-md-10">
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by code, name, email, or phone">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <?php if (!empty($members)): ?>
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Member Code</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><?php echo (int) $member['id']; ?></td>
                                        <td><?php echo htmlspecialchars($member['member_code']); ?></td>
                                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($member['phone'] ?? '—'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $member['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($member['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['joined_at']); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="member-details.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-outline-info">View</a>
                                                <a href="edit-member.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-outline-primary">Edit</a>
                                                <a href="delete-member.php?id=<?php echo (int) $member['id']; ?>" class="btn btn-outline-danger">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-light text-center">No members found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
