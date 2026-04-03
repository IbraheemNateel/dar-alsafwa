<?php
/**
 * نظام الإشعارات الداخلية للطلاب
 * بديل لنظام الرسائل النصية SMS
 */

function createStudentNotification($pdo, $studentId, $type, $title, $message, $payload = null) {
    $stmt = $pdo->prepare("
        INSERT INTO student_notifications (student_id, type, title, message, payload) 
        VALUES (?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$studentId, $type, $title, $message, $payload ? json_encode($payload) : null]);
}

function generateFollowupNotification($pdo, $studentId, $studentName, $followupData) {
    $title = "إشعار بمتابعة التسميع";
    $date = date('Y/m/d', strtotime($followupData['followup_date']));
    $dayName = $followupData['day_name'] ?? 'اليوم';
    $message = "تم تسجيل متابعة ليوم $dayName الموافق $date:\n\n";
    $message .= "• الحفظ: من " . ($followupData['memorization_from'] ?: '-') . " إلى " . ($followupData['memorization_to'] ?: '-') . " بتقييم " . $followupData['memorization_rating'] . "/5\n";
    $message .= "• المراجعة: من " . ($followupData['review_from'] ?: '-') . " إلى " . ($followupData['review_to'] ?: '-') . " بتقييم " . $followupData['review_rating'] . "/5\n";
    $message .= "• السلوك: التقييم " . $followupData['behavior_rating'] . "/10";
    
    if (!empty($followupData['notes'])) {
        $message .= "\n\n💡 ملاحظة: " . $followupData['notes'];
    }
    
    return createStudentNotification($pdo, $studentId, 'followup', $title, $message, $followupData);
}

function generateAbsenceNotification($pdo, $studentId, $studentName, $absenceDate) {
    $title = "تسجيل غياب";
    $message = "تم تسجيل غياب في يوم " . $absenceDate . "\n\n";
    $message .= "⚠️ يرجى متابعة سبب الغياب والتواصل مع ولي الأمر.";
    
    return createStudentNotification($pdo, $studentId, 'absence', $title, $message, ['date' => $absenceDate]);
}

function generateBroadcastNotification($pdo, $studentId, $studentName, $title, $message) {
    if (empty(trim($title))) {
        $title = "إشعار عام / درس جماعي";
    }
    return createStudentNotification($pdo, $studentId, 'broadcast', $title, $message, ['broadcast' => true]);
}

function generateBehaviorWarningNotification($pdo, $studentId, $studentName, $followupData) {
    $title = "إشعار بشأن السلوك";
    $date = date('Y/m/d', strtotime($followupData['followup_date']));
    $message = "تم تسجيل تقييم متدنٍ للسلوك في يوم $date\n\n";
    $message .= "🚫 التقييم الممنوح للطالب هو: " . $followupData['behavior_rating'] . "/10\n\n";
    $message .= "نرجو من ولي الأمر المتابعة جيداً والانتباه لتحسين سلوك الطالب في الأيام القادمة لما فيه مصلحته، بارك الله فيكم.";
    
    return createStudentNotification($pdo, $studentId, 'warning_behavior', $title, $message, $followupData);
}

function generateMemorizationWarningNotification($pdo, $studentId, $studentName, $followupData) {
    $title = "إشعار بمتابعة الحفظ والمراجعة";
    $date = date('Y/m/d', strtotime($followupData['followup_date']));
    $message = "تم تسجيل ضعف في أداء الطالب اليوم الموافق $date:\n\n";
    $message .= "📚 تقييم الحفظ: " . $followupData['memorization_rating'] . "/5\n";
    $message .= "📝 تقييم المراجعة: " . $followupData['review_rating'] . "/5\n\n";
    $message .= "نرجو من ولي الأمر المتابعة جيداً، وزيادة الجهد في المنزل للرقي بمستوى الطالب في الحفظ والمراجعة.";
    
    return createStudentNotification($pdo, $studentId, 'warning_memorization', $title, $message, $followupData);
}

function shouldGenerateNotifications() {
    $now = new DateTime('now');
    $cutoff = new DateTime('today 21:00');
    return $now >= $cutoff;
}

function processDailyNotifications($pdo) {
    if (!shouldGenerateNotifications()) {
        return;
    }
    
    $today = date('Y-m-d');
    
    // معالجة المتابعات اليومية
    $stmt = $pdo->prepare("
        SELECT df.*, s.full_name, s.id as student_id 
        FROM daily_followup df 
        JOIN students s ON df.student_id = s.id 
        WHERE df.followup_date = ? 
        AND NOT EXISTS (
            SELECT 1 FROM sent_notifications sn 
            WHERE sn.student_id = s.id 
            AND sn.notification_type = 'followup' 
            AND sn.notification_date = ?
        )
    ");
    $stmt->execute([$today, $today]);
    $followups = $stmt->fetchAll();
    
    foreach ($followups as $followup) {
        generateFollowupNotification($pdo, $followup['student_id'], $followup['full_name'], $followup);
        
        // تسجيل الإشعار المرسل لتجنب التكرار
        $sentStmt = $pdo->prepare("
            INSERT IGNORE INTO sent_notifications (student_id, notification_type, notification_date) 
            VALUES (?, 'followup', ?)
        ");
        $sentStmt->execute([$followup['student_id'], $today]);
    }
    
    // معالجة الغياب أصبحت يدوية عبر processManualAbsences ولا تتم هنا ليلاً
    
    // معالجة إنذارات السلوك
    $stmt = $pdo->prepare("
        SELECT df.*, s.full_name, s.id as student_id 
        FROM daily_followup df 
        JOIN students s ON df.student_id = s.id 
        WHERE df.followup_date = ? 
        AND df.behavior_rating <= 2
        AND NOT EXISTS (
            SELECT 1 FROM sent_notifications sn 
            WHERE sn.student_id = s.id 
            AND sn.notification_type = 'warning_behavior' 
            AND sn.notification_date = ?
        )
    ");
    $stmt->execute([$today, $today]);
    $behaviorWarnings = $stmt->fetchAll();
    
    foreach ($behaviorWarnings as $warning) {
        generateBehaviorWarningNotification($pdo, $warning['student_id'], $warning['full_name'], $warning);
        
        $sentStmt = $pdo->prepare("
            INSERT IGNORE INTO sent_notifications (student_id, notification_type, notification_date) 
            VALUES (?, 'warning_behavior', ?)
        ");
        $sentStmt->execute([$warning['student_id'], $today]);
    }
    
    // معالجة إنذارات الحفظ/المراجعة
    $stmt = $pdo->prepare("
        SELECT df.*, s.full_name, s.id as student_id 
        FROM daily_followup df 
        JOIN students s ON df.student_id = s.id 
        WHERE df.followup_date = ? 
        AND (df.memorization_rating <= 2 OR df.review_rating <= 2)
        AND NOT EXISTS (
            SELECT 1 FROM sent_notifications sn 
            WHERE sn.student_id = s.id 
            AND sn.notification_type = 'warning_memorization' 
            AND sn.notification_date = ?
        )
    ");
    $stmt->execute([$today, $today]);
    $memorizationWarnings = $stmt->fetchAll();
    
    foreach ($memorizationWarnings as $warning) {
        generateMemorizationWarningNotification($pdo, $warning['student_id'], $warning['full_name'], $warning);
        
        $sentStmt = $pdo->prepare("
            INSERT IGNORE INTO sent_notifications (student_id, notification_type, notification_date) 
            VALUES (?, 'warning_memorization', ?)
        ");
        $sentStmt->execute([$warning['student_id'], $today]);
    }
}

/**
 * دالة يدوية لاعتماد الغيابات
 * تفحص من لم يحصل على متابعة اليوم وتقوم بإرسال الغياب له
 * تعود بعدد الطلاب الذين تم تسجيلهم غائبين
 */
function processManualAbsences($pdo, $date) {
    // جلب الطلاب الذين لم يتم تقييمهم
    $stmt = $pdo->prepare("
        SELECT s.full_name, s.id as student_id
        FROM students s
        WHERE NOT EXISTS (
            SELECT 1 FROM daily_followup df 
            WHERE df.student_id = s.id 
            AND df.followup_date = ?
        )
        AND NOT EXISTS (
            SELECT 1 FROM sent_notifications sn 
            WHERE sn.student_id = s.id 
            AND sn.notification_type = 'absence' 
            AND sn.notification_date = ?
        )
    ");
    $stmt->execute([$date, $date]);
    $absentStudents = $stmt->fetchAll();
    
    $count = 0;
    foreach ($absentStudents as $student) {
        // إرسال إشعار الغياب للطالب
        generateAbsenceNotification($pdo, $student['student_id'], $student['full_name'], $date);
        
        // لتسجيل أنه تم الإرسال منعا للتكرار
        $sentStmt = $pdo->prepare("
            INSERT IGNORE INTO sent_notifications (student_id, notification_type, notification_date) 
            VALUES (?, 'absence', ?)
        ");
        $sentStmt->execute([$student['student_id'], $date]);
        
        $count++;
    }
    
    return $count;
}
?>
