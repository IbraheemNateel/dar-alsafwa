<?php
/**
 * دار صفوة - المصادقة والجلسات
 * Dar Safwa - Authentication & Sessions
 */

session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        require_once __DIR__ . '/functions.php';
        header('Location: ' . getBaseUrl() . 'index.php');
        exit;
    }
}

function logout(): void {
    session_destroy();
    require_once __DIR__ . '/functions.php';
    header('Location: ' . getBaseUrl() . 'index.php');
    exit;
}
