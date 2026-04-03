<?php
/**
 * معالجة إرسال إشعار عام / درس جماعي لجميع الطلبة
 * يقوم أيضاً بإغلاق اليوم لمنع احتساب الغياب
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

// التأكد من أن الدور ليس طالباً
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header('Location: ../student/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification-helper.php';

$pdo = getConnection();
$today = date('Y-m-d');

$title = trim($_POST['broadcast_title'] ?? '');
$message = trim($_POST['broadcast_message'] ?? '');

if (empty($message)) {
    $_SESSION['error_message'] = "يرجى كتابة نص الرسالة قبل الإرسال.";
    header('Location: index.php');
    exit;
}

try {
    // جلب جميع الطلبة
    $stmt = $pdo->query("SELECT id, full_name FROM students");
    $students = $stmt->fetchAll();
    
    $count = 0;
    foreach ($students as $student) {
        generateBroadcastNotification($pdo, $student['id'], $student['full_name'], $title, $message);
        $count++;
    }
    
    // إغلاق اليوم لمنع احتساب أي غياب
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_closure_log (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        closure_date DATE UNIQUE, 
        closed_by_user_id INT, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO daily_closure_log (closure_date, closed_by_user_id) VALUES (?, ?)");
    $stmt->execute([$today, $_SESSION['user_id'] ?? 0]);
    
    $_SESSION['success_message'] = "تم إرسال الإشعار بنجاح إلى ($count) طالب، وتم إغلاق اليوم (لن يُحتسب غياب).";
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "حدث خطأ أثناء الإرسال: " . $e->getMessage();
}

header('Location: index.php');
exit;
?>
