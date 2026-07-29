<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('db.php');

$pageTitle = 'My Profile';
$message = '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$user = getUserProfileData($conn, (int) $_SESSION['user_id']);

if ($user === null) {
    $message = 'Unable to load profile.';
    $user = [];
}

$user['display_name'] = $user['full_name'] ?? $user['name'] ?? '';
$user['first_name'] = $user['first_name'] ?? '';
$user['last_name'] = $user['last_name'] ?? '';
$user['phone'] = $user['phone'] ?? '';
$user['address'] = $user['address'] ?? '';
$user['profile_image'] = $user['profile_image'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileData = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'profile_image' => trim($_POST['profile_image'] ?? ''),
    ];

    updateUserProfileData($conn, (int) $_SESSION['user_id'], $profileData);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully.'];
    header('Location: profile.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <main>
        <section class="welcome form-card-container">
            <h2>My Profile</h2>
            <?php if ($flash): ?>
                <div class="flash <?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>
            <?php if ($message !== ''): ?>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <form method="post" action="profile.php" class="book-form">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" disabled>

                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>

                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">

                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">

                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">

                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">

                <label for="profile_image">Profile Image URL</label>
                <input type="text" id="profile_image" name="profile_image" value="<?php echo htmlspecialchars($user['profile_image'] ?? ''); ?>">

                <button type="submit">Save Profile</button>
            </form>
        </section>
    </main>
</body>
</html>
