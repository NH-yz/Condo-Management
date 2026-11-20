<?php
// index.php - Main entry router

require_once __DIR__ . '/includes_auth.php';

if (isLoggedIn()) {
    $u = currentUser();
    if ($u['role'] === 'admin') {
        header('Location: admin-dashboard.php');
    } else {
        header('Location: resident-home.php');
    }
    exit;
}

header('Location: login.php');
exit;
