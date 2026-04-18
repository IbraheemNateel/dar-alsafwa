<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $pdo = getConnection();
    
    // We shouldn't require auth rigidly if session expired offline, 
    // but typically cookie persists. Let's assume auth is valid for now.
    require_once __DIR__ . '/../../includes/sms-helper.php';
    require_once __DIR__ . '/../../includes/notification-helper.php';

    $synced_count = 0;

    foreach ($data as $row) {
        $student_id = (int)($row['student_id'] ?? 0);
        if ($student_id <= 0) continue;

        // Fetch student for name/phone etc
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        if (!$student) continue;

        $followup_date = $row['followup_date'] ?? date('Y-m-d');
        $dayNamesAr = ['Sunday' => 'الأحد', 'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'];
        $dayEn = date('l', strtotime($followup_date));
        $day_name = $dayNamesAr[$dayEn] ?? $dayEn;
        $followup_time = $row['followup_time'] ?? date('H:i:s');
        $memorization_from = trim($row['memorization_from'] ?? '');
        $memorization_to = trim($row['memorization_to'] ?? '');
        $memorization_rating = (int)($row['memorization_rating'] ?? 0);
        $review_from = trim($row['review_from'] ?? '');
        $review_to = trim($row['review_to'] ?? '');
        $review_rating = (int)($row['review_rating'] ?? 0);
        $behavior_rating = (int)($row['behavior_rating'] ?? 0);
        $notes = trim($row['notes'] ?? '');

        // Insert
        $stmt = $pdo->prepare("
            INSERT INTO daily_followup (student_id, followup_date, day_name, followup_time, 
                memorization_from, memorization_to, memorization_rating, 
                review_from, review_to, review_rating, behavior_rating, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                followup_time=VALUES(followup_time), memorization_from=VALUES(memorization_from),
                memorization_to=VALUES(memorization_to), memorization_rating=VALUES(memorization_rating),
                review_from=VALUES(review_from), review_to=VALUES(review_to), review_rating=VALUES(review_rating),
                behavior_rating=VALUES(behavior_rating), notes=VALUES(notes)
        ");
        $stmt->execute([
            $student_id, $followup_date, $day_name, $followup_time,
            $memorization_from, $memorization_to, $memorization_rating,
            $review_from ?: null, $review_to ?: null, $review_rating,
            $behavior_rating, $notes ?: null
        ]);

        sendFollowupSMS($student, $memorization_from, $memorization_to, $memorization_rating, $review_from ?: null, $review_to ?: null, $review_rating, $behavior_rating);

        generateFollowupNotification($pdo, $student_id, $student['full_name'], [
            'followup_date' => $followup_date,
            'memorization_from' => $memorization_from, 'memorization_to' => $memorization_to,
            'memorization_rating' => $memorization_rating, 'review_from' => $review_from,
            'review_to' => $review_to, 'review_rating' => $review_rating,
            'behavior_rating' => $behavior_rating, 'notes' => $notes
        ]);
        
        $followupDataForNotify = [
            'followup_date' => $followup_date, 'memorization_rating' => $memorization_rating,
            'review_rating' => $review_rating, 'behavior_rating' => $behavior_rating
        ];
        
        if ($memorization_rating < 3 || $review_rating < 3) {
            generateMemorizationWarningNotification($pdo, $student_id, $student['full_name'], $followupDataForNotify);
        }
        if ($behavior_rating <= 5) {
            generateBehaviorWarningNotification($pdo, $student_id, $student['full_name'], $followupDataForNotify);
        }

        $synced_count++;
    }

    echo json_encode(['success' => true, 'synced_count' => $synced_count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
