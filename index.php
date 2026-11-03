<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
