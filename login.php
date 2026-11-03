
<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$identifier = '';
$cooldownSeconds = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $identifier = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $genericError = 'Invalid username or password';

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT id, username, password_hash, role,
                   failed_attempts, locked_until,
                   CASE
                       WHEN locked_until IS NOT NULL
                       AND locked_until > NOW()
                       THEN TIMESTAMPDIFF(SECOND, NOW(), locked_until)
                       ELSE 0
                   END AS lock_remaining
            FROM users
            WHERE username = :username
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'username' => $identifier
        ]);

        $user = $stmt->fetch();

        if ($user) {

            // CHECK ACTIVE COOLDOWN
            if ((int)$user['lock_remaining'] > 0) {

                $cooldownSeconds = (int)$user['lock_remaining'];

                $pdo->commit();

                $error = 'Account temporarily locked. Please wait for the cooldown.';

            } else {

                // RESET EXPIRED LOCK
                if ($user['locked_until'] !== null) {

                    $reset = $pdo->prepare("
                        UPDATE users
                        SET failed_attempts = 0,
                            locked_until = NULL
                        WHERE id = :id
                    ");

                    $reset->execute([
                        'id' => $user['id']
                    ]);

                    $user['failed_attempts'] = 0;
                }

                // VERIFY PASSWORD
                if (password_verify($password, $user['password_hash'])) {

                    // RESET FAILED ATTEMPTS
                    $clear = $pdo->prepare("
                        UPDATE users
                        SET failed_attempts = 0,
                            locked_until = NULL
                        WHERE id = :id
                    ");

                    $clear->execute([
                        'id' => $user['id']
                    ]);

                    $pdo->commit();

                    // REGENERATE SESSION ID
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();

                    header('Location: dashboard.php');
                    exit;

                } else {

                    // INCREMENT FAILED ATTEMPTS
                    $attempts = (int)$user['failed_attempts'] + 1;

                    if ($attempts >= 5) {

                        // LOCK ACCOUNT FOR 15 MINUTES
                        $lock = $pdo->prepare("
                            UPDATE users
                            SET failed_attempts = 5,
                                locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                            WHERE id = :id
                        ");

                        $lock->execute([
                            'id' => $user['id']
                        ]);

                        $cooldownSeconds = 900;

                    } else {

                        $update = $pdo->prepare("
                            UPDATE users
                            SET failed_attempts = :attempts
                            WHERE id = :id
                        ");

                        $update->execute([
                            'attempts' => $attempts,
                            'id' => $user['id']
                        ]);
                    }

                    $pdo->commit();

                    $error = $genericError;
                }
            }

        } else {

            $pdo->commit();

            password_verify(
                $password,
                '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2y5/6z9f8eK'
            );

            $error = $genericError;
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Login error: ' . $e->getMessage());

        $error = 'Something went wrong. Please try again.';
    }
}
?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Sign in | Secure Login</title>

    <link rel="stylesheet" href="assets/style.css">

    <style>
        .btn:disabled {
            background: #94a3b8 !important;
            cursor: not-allowed !important;
            opacity: 0.75;
            transform: none !important;
        }

        .cooldown-box {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #9f1239;
            padding: 18px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 20px;
        }

        .cooldown-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .cooldown-timer {
            font-size: 35px;
            font-weight: 800;
            letter-spacing: 2px;
            font-variant-numeric: tabular-nums;
        }

        .cooldown-description {
            font-size: 12px;
            margin-top: 7px;
            color: #9f1239;
        }

        .locked-input {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .attempt-warning {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
        }
    </style>

</head>

<body>

<main class="auth-wrap">

    <section class="auth-card">

        <div class="brand-mark">SL</div>

        <h1>Welcome back</h1>

        <p class="muted">
            Sign in to continue to your account.
        </p>

        <?php if (isset($_GET['registered'])): ?>

            <div class="alert success">
                Registration successful. You can now sign in.
            </div>

        <?php endif; ?>

        <?php if ($error !== ''): ?>

            <div class="alert error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if ($cooldownSeconds > 0): ?>

            <div class="cooldown-box">

                <div class="cooldown-title">
                    ACCOUNT TEMPORARILY LOCKED
                </div>

                <div class="cooldown-timer" id="countdown">
                    15:00
                </div>

                <div class="cooldown-description">
                    Too many failed login attempts.
                    Please wait before trying again.
                </div>

            </div>

        <?php endif; ?>

        <form
            method="post"
            action="login.php"
            autocomplete="on"
            id="loginForm"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <label>Username</label>

            <input
                type="text"
                name="username"
                id="username"
                value="<?= e($identifier) ?>"
                required
                autocomplete="username"
                <?= $cooldownSeconds > 0 ? 'disabled' : '' ?>
                class="<?= $cooldownSeconds > 0 ? 'locked-input' : '' ?>"
            >

            <label>Password</label>

            <input
                type="password"
                name="password"
                id="password"
                required
                autocomplete="current-password"
                <?= $cooldownSeconds > 0 ? 'disabled' : '' ?>
                class="<?= $cooldownSeconds > 0 ? 'locked-input' : '' ?>"
            >

            <button
                class="btn"
                type="submit"
                id="loginButton"
                <?= $cooldownSeconds > 0 ? 'disabled' : '' ?>
            >
                <?= $cooldownSeconds > 0 ? 'Please wait...' : 'Sign in' ?>
            </button>

            <?php if ($cooldownSeconds > 0): ?>

                <p class="attempt-warning" id="waitMessage">
                    Login will be available when the countdown ends.
                </p>

            <?php endif; ?>

        </form>

        <p class="below">
            No account yet?
            <a href="register.php">Create one</a>
        </p>

    </section>

</main>

<?php if ($cooldownSeconds > 0): ?>

<script>
(function () {

    let remaining = <?= (int)$cooldownSeconds ?>;

    const countdown = document.getElementById('countdown');
    const loginButton = document.getElementById('loginButton');
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const waitMessage = document.getElementById('waitMessage');
    const loginForm = document.getElementById('loginForm');

    // Prevent form submission during cooldown.
    loginForm.addEventListener('submit', function(event) {
        if (remaining > 0) {
            event.preventDefault();
        }
    });

    // Format countdown as MM:SS.
    function updateCountdown() {

        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        countdown.textContent =
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');

        if (remaining <= 0) {

            countdown.textContent = '00:00';

            loginButton.disabled = false;
            loginButton.textContent = 'Sign in';

            username.disabled = false;
            password.disabled = false;

            username.classList.remove('locked-input');
            password.classList.remove('locked-input');

            waitMessage.textContent =
                'Cooldown finished. You can now try logging in again.';

            // Reload to synchronize with the database.
            setTimeout(function() {
                window.location.reload();
            }, 1000);

            return;
        }

        remaining--;

        setTimeout(updateCountdown, 1000);
    }

    updateCountdown();

})();
</script>

<?php endif; ?>

</body>
</html>


<box border radius="lg" padding={3} gap={3}>
  <box gap={1}>
    <badge color="danger">Account temporarily locked</badge>
   
  </box>
  <box background="surface-secondary" radius="lg" padding={3} align="center" gap={1}>
    <title size="2xl" color="danger" tabularNums>14:59</title>
    <text color="secondary" size="xs">Please wait before trying again.</text>
  </box>
  <box background="surface-tertiary" radius="md" padding={3} align="center">
    <text color="secondary" weight="semibold">Please wait...</text>
    <text color="secondary" size="xs">Login button disabled</text>
  </box>
</box>

