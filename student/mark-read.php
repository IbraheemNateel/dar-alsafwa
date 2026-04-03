<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$notification_id = (int)($_REQUEST['id'] ?? 0);

if ($notification_id > 0) {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("UPDATE student_notifications SET read_at = NOW() WHERE id = ? AND student_id = ? AND read_at IS NULL");
    $stmt->execute([$notification_id, $_SESSION['student_id']]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>