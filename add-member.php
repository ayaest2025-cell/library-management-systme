<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'Add Member';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$member = [
    'member_code' => '',
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'status' => 'Active',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member['member_code'] = trim($_POST['member_code'] ?? '');
    $member['full_name'] = trim($_POST['full_name'] ?? '');
    $member['email'] = trim($_POST['email'] ?? '');
    $member['phone'] = trim($_POST['phone'] ?? '');
    $member['address'] = trim($_POST['address'] ?? '');
    $member['status'] = trim($_POST['status'] ?? 'Active');

    if ($member['member_code'] === '' || $member['full_name'] === '') {
        $errorMessage = 'Member code and full name are required.';
    } elseif (strlen($member['member_code']) > 50) {
        $errorMessage = 'Member code must be 50 characters or less.';
    } elseif (strlen($member['full_name']) > 150) {
        $errorMessage = 'Full name must be 150 characters or less.';
    } elseif ($member['email'] !== '' && !filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($member['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{4,30}$/', $member['phone'])) {
        $errorMessage = 'Please enter a valid phone number.';
    } elseif (strlen($member['address']) > 255) {
        $errorMessage = 'Address must be 255 characters or less.';
    } elseif (!in_array($member['status'], ['Active', 'Inactive'], true)) {
        $errorMessage = 'Please choose a valid membership status.';
    } else {
        $checkStmt = $conn->prepare('SELECT id FROM members WHERE member_code = ? LIMIT 1');
        $checkStmt->bind_param('s', $member['member_code']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $errorMessage = 'A member with that code already exists.';
        } else {
            $insertStmt = $conn->prepare('INSERT INTO members (member_code, full_name, email, phone, address, status) VALUES (?, ?, ?, ?, ?, ?)');
            $insertStmt->bind_param('ssssss', $member['member_code'], $member['full_name'], $member['email'], $member['phone'], $member['address'], $member['status']);
            $insertStmt->execute();

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Member added successfully.'];
            header('Location: members.php');
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
                <h2 class="mb-3">Add New Member</h2>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] === 'success' ? 'success' : 'danger'); ?>">
                        <?php echo htmlspecialchars($flash['message']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <form method="post" action="add-member.php" class="row g-3">
                    <div class="col-md-6">
                        <label for="member_code" class="form-label">Member Code</label>
                        <input type="text" id="member_code" name="member_code" class="form-control" value="<?php echo htmlspecialchars($member['member_code']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($member['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($member['email']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($member['phone']); ?>">
                    </div>
                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($member['address']); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="Active" <?php echo $member['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $member['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Member</button>
                        <a href="members.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
