<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

// Optional 30-minute inactivity timeout.
$timeout = 1800;
if (isset($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > $timeout) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard | Secure Login</title><link rel="stylesheet" href="assets/style.css">
</head><body>
<header class="topbar"><strong>Secure Login System</strong><a class="logout-link" href="logout.php">Log out</a></header>
<main class="dashboard">
<section class="welcome-card"><span class="eyebrow">ACCOUNT DASHBOARD</span>
<h1>Hello, <?= e((string)$_SESSION['username']) ?>!</h1>
<p>You have successfully signed in. Your session is active.</p>
<div class="details"><div><small>User ID</small><strong><?= (int)$_SESSION['user_id'] ?></strong></div><div><small>Role</small><strong><?= e((string)$_SESSION['role']) ?></strong></div></div>
</section>
</main></body></html>
