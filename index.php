<?php
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . (BASE_PATH ?: '') . '/pages/dashboard.php');
} else {
    header('Location: ' . (BASE_PATH ?: '') . '/login.php');
}
exit;
