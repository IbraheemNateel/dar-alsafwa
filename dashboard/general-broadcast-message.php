<?php
/**
 * معالجة إرسال إشعار عام لجميع الطلبة
 * لا يتدخل في أمر الغياب ولا يغلق اليوم
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
    
    $_SESSION['success_message'] = "تم إرسال الإشعار الجماعي بنجاح إلى ($count) طالب، (لم يتم التأثير على الغياب اليومي).";
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "حدث خطأ أثناء الإرسال: " . $e->getMessage();
}

header('Location: index.php');
exit;
?>
