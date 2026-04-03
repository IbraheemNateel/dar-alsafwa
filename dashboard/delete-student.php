<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: all-students.php'); exit; }

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();
$pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
header('Location: all-students.php');
exit;
