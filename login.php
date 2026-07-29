<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$email = '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session has expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $user = getUserByEmail($conn, $email);
        $storedPassword = $user !== null ? ($user['password_hash'] ?? $user['password'] ?? '') : '';
        $passwordMatch = $storedPassword !== '' && password_verify($password, $storedPassword);
        $legacyPassword = !$passwordMatch && $storedPassword !== '' && hash_equals($storedPassword, $password);

        if ($passwordMatch || $legacyPassword) {
            // Upgrade old plaintext credentials as soon as their owner signs in.
            if ($legacyPassword) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $conn->prepare('UPDATE users SET password = ?, password_hash = ? WHERE id = ?');
                $upgrade->bind_param('ssi', $newHash, $newHash, $user['id']);
                $upgrade->execute();
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'] ?: ($user['name'] ?: $user['email']);
            $_SESSION['user_role'] = $user['role'] ?? 'borrower';
            header('Location: index.php');
            exit();
        }

        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <main class="container" style="max-width: 440px">
        <section class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 text-center mb-2">Welcome back</h1>
                <p class="text-muted text-center mb-4">Sign in to manage your library.</p>
                <?php if ($flash): ?><div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>" role="alert"><?php echo htmlspecialchars($flash['message']); ?></div><?php endif; ?>
                <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                    <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" type="email" id="email" name="email" autocomplete="email" required value="<?php echo htmlspecialchars($email); ?>"></div>
                    <div class="mb-4"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required></div>
                    <button class="btn btn-primary w-100" type="submit">Sign in</button>
                </form>
                <p class="text-center text-muted mt-4 mb-0">New member? <a href="register.php">Create an account</a></p>
            </div>
        </section>
    </main>
</body>
</html>
