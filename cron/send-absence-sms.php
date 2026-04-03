<?php
/**
 * دار صفوة - إرسال رسائل الغياب التلقائية
 * Dar Safwa - Send Absence SMS Cron Job
 *
 * التشغيل: يومياً عند الساعة 9 مساءً (21:00)
 * - Crontab (Linux): 0 21 * * * php /path/to/dar-safwa/cron/send-absence-sms.php
 * - Task Scheduler (Windows): أنشئ مهمة يومية تشغّل php.exe مع المسار أعلاه
 *
 * استثناء: لا يُرسل يوم الجمعة (يوم إجازة)
 */

if (date('N') == 5) exit; // الجمعة - لا إرسال

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sms-helper.php';

$pdo = getConnection();

$today = date('Y-m-d');
$presentIds = $pdo->prepare("SELECT student_id FROM daily_followup WHERE followup_date = ?");
$presentIds->execute([$today]);
$presentIds = $presentIds->fetchAll(PDO::FETCH_COLUMN);

$dayNamesAr = ['Sunday' => 'الأحد', 'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'];
$dayName = $dayNamesAr[date('l')] ?? date('l');

$stmt = $pdo->query("SELECT id, full_name, guardian_phone FROM students");
$insertStmt = $pdo->prepare("INSERT INTO sent_notifications (student_id, notification_type, notification_date) VALUES (?, 'absence', ?)");

while ($student = $stmt->fetch()) {
    if (in_array($student['id'], $presentIds)) continue;

    $check = $pdo->prepare("SELECT id FROM sent_notifications WHERE student_id=? AND notification_type='absence' AND notification_date=?");
    $check->execute([$student['id'], $today]);
    if ($check->fetch()) continue;

    $message = "الطالب {$student['full_name']} قد تغيّب عن حضور حلقة التحفيظ ليوم {$dayName} بتاريخ {$today}. رجاء متابعة الأمر. - دار صفوة";

    if (sendSMS($student['guardian_phone'], $message)) {
        $insertStmt->execute([$student['id'], $today]);
        
        // إضافة إشعار للطالب
        $notificationStmt = $pdo->prepare("INSERT INTO student_notifications (student_id, type, title, message) VALUES (?, 'absence', 'غياب', ?)");
        $notificationStmt->execute([$student['id'], $message]);
    }
}
