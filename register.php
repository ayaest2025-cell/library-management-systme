<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session has expired. Please try again.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter your name and a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must be at least 8 characters long.';
    } elseif (!hash_equals($password, $confirmation)) {
        $error = 'The passwords do not match.';
    } else {
        $existing = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $existing->bind_param('s', $email);
        $existing->execute();

        if ($existing->get_result()->num_rows > 0) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'borrower';
            $insert = $conn->prepare('INSERT INTO users (name, full_name, email, password, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->bind_param('ssssss', $name, $name, $email, $hash, $hash, $role);

            if ($insert->execute()) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account created. You can now sign in.'];
                header('Location: login.php');
                exit();
            }

            $error = 'Unable to create your account. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Create account | Library System</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center min-vh-100"><main class="container" style="max-width:440px"><section class="card shadow-sm border-0"><div class="card-body p-4 p-md-5"><h1 class="h3 text-center mb-2">Create an account</h1><p class="text-muted text-center mb-4">Join the library system.</p><?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><form method="post" novalidate><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>"><div class="mb-3"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" autocomplete="name" required value="<?php echo htmlspecialchars($name); ?>"></div><div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" type="email" id="email" name="email" autocomplete="email" required value="<?php echo htmlspecialchars($email); ?>"></div><div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required><div class="form-text">At least 8 characters.</div></div><div class="mb-4"><label class="form-label" for="password_confirmation">Confirm password</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div><button class="btn btn-primary w-100" type="submit">Create account</button></form><p class="text-center text-muted mt-4 mb-0">Already registered? <a href="login.php">Sign in</a></p></div></section></main></body>
</html>
