<?php
require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors[] = 'Username must be 3–30 characters and contain only letters, numbers, or underscores.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8 || strlen($password) > 4096) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
        $check->execute(['username' => $username, 'email' => $email]);
        if ($check->fetch()) {
            $errors[] = 'Username or email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare(
                "INSERT INTO users (username, email, password_hash, role, created_at)
                 VALUES (:username, :email, :password_hash, 'user', NOW())"
            );
            $insert->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => $hash,
            ]);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create account | Secure Login</title><link rel="stylesheet" href="assets/style.css">
</head><body>
<main class="auth-wrap"><section class="auth-card">
<div class="brand-mark">SL</div><h1>Create account</h1><p class="muted">Register a new secure account.</p>
<?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" action="register.php" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<label>Username</label><input name="username" value="<?= e($username) ?>" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" autocomplete="username">
<label>Email</label><input type="email" name="email" value="<?= e($email) ?>" required maxlength="190" autocomplete="email">
<label>Password</label><input type="password" name="password" required minlength="8" autocomplete="new-password">
<label>Confirm password</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
<button class="btn" type="submit">Create account</button>
</form>
<p class="below">Already registered? <a href="login.php">Sign in</a></p>
</section></main></body></html>
