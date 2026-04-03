<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

// التأكد من أن الدور ليس طالباً
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header('Location: ../student/index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification-helper.php';

$pdo = getConnection();
$today = date('Y-m-d');

try {
    // التأكد من أن الطلب POST 
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("طريقة غير مسموحة");
    }
    
    // استدعاء الدالة اليدوية لاعتماد الغيابات
    $absentCount = processManualAbsences($pdo, $today);
    
    // تسجيل الاعتماد لهذا اليوم لمنع التكرار بصريا وبرمجيا 
    $stmt = $pdo->prepare("INSERT IGNORE INTO daily_closure_log (closure_date, closed_by_user_id) VALUES (?, ?)");
    if ($stmt) {
        // إذا لم يكن الجدول موجودا سيتجاهل الخطأ لكن الفحص الأساسي موجود في دالة الغياب
        try {
            $stmt->execute([$today, $_SESSION['user_id'] ?? 0]);
        } catch(PDOException $e) {
            // تجاهل خطأ عدم وجود الجدول إن لم يتم إنشاءه، لأننا نعتمد على جدول sent_notifications بصورة أساسية
        }
    }
    
    $_SESSION['success_message'] = "تم بنجاح إغلاق اليوم! تم إرسال ($absentCount) إشعار غياب للطلاب المتغيبين.";
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "حدث خطأ أثناء اعتماد الغيابات: " . $e->getMessage();
}

// إعادة التوجيه للصفحة الرئيسية
header('Location: index.php');
exit;
?>
