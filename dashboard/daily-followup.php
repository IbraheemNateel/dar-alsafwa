<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'إدخال المتابعة اليومية';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();
$foundStudent = null;
$matchedStudents = [];
$success = '';
$error = '';
$search = trim($_GET['search'] ?? '');
$selectedStudentId = (int)($_GET['student_id'] ?? 0);

if ($selectedStudentId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stmt->execute([$selectedStudentId]);
    $foundStudent = $stmt->fetch();
} elseif ($search) {
    $firstName = trim(explode(' ', $search)[0] ?? '');

    if ($firstName !== '' && !ctype_digit($search)) {
        $stmt = $pdo->prepare("SELECT id, full_name, student_id, guardian_phone FROM students WHERE full_name LIKE ? ORDER BY full_name LIMIT 50");
        $stmt->execute(["$firstName %"]);
        $matchedStudents = $stmt->fetchAll();

        if (count($matchedStudents) === 1) {
            $selectedStudentId = (int)$matchedStudents[0]['id'];
            $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
            $stmt->execute([$selectedStudentId]);
            $foundStudent = $stmt->fetch();
            $matchedStudents = [];
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? OR full_name LIKE ? LIMIT 1");
        $stmt->execute([$search, "%$search%"]);
        $foundStudent = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)($_POST['student_id'] ?? 0);
    if ($student_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$student_id]);
        $foundStudent = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $foundStudent) {
    $student_id = (int)$foundStudent['id'];
    $followup_date = $_POST['followup_date'] ?? date('Y-m-d');
    $dayNamesAr = ['Sunday' => 'الأحد', 'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة', 'Saturday' => 'السبت'];
    $dayEn = date('l', strtotime($followup_date));
    $day_name = $dayNamesAr[$dayEn] ?? $dayEn;
    $followup_time = $_POST['followup_time'] ?? date('H:i:s');
    $memorization_from = trim($_POST['memorization_from'] ?? '');
    $memorization_to = trim($_POST['memorization_to'] ?? '');
    $memorization_rating = (int)($_POST['memorization_rating'] ?? 0);
    $review_from = trim($_POST['review_from'] ?? '');
    $review_to = trim($_POST['review_to'] ?? '');
    $review_rating = (int)($_POST['review_rating'] ?? 0);
    $behavior_rating = (int)($_POST['behavior_rating'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (empty($memorization_from) || empty($memorization_to)) {
        $error = 'يرجى إدخال الحفظ من وإلى';
    } else {
        try {
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

            // إرسال SMS لأولياء الأمور (اختياري)
            require_once __DIR__ . '/../includes/sms-helper.php';
            sendFollowupSMS($foundStudent, $memorization_from, $memorization_to, $memorization_rating, $review_from ?: null, $review_to ?: null, $review_rating, $behavior_rating);

            // إضافة إشعار للطالب (نظام الإشعارات الداخلية)
            require_once __DIR__ . '/../includes/notification-helper.php';
            generateFollowupNotification($pdo, $student_id, $foundStudent['full_name'], [
                'followup_date' => $followup_date,
                'memorization_from' => $memorization_from,
                'memorization_to' => $memorization_to,
                'memorization_rating' => $memorization_rating,
                'review_from' => $review_from,
                'review_to' => $review_to,
                'review_rating' => $review_rating,
                'behavior_rating' => $behavior_rating,
                'notes' => $notes
            ]);
            
            // إضافة إنذارات فورية إذا كان التقييم متدنياً
            $followupDataForNotify = [
                'followup_date' => $followup_date,
                'memorization_rating' => $memorization_rating,
                'review_rating' => $review_rating,
                'behavior_rating' => $behavior_rating
            ];
            
            if ($memorization_rating < 3 || $review_rating < 3) {
                generateMemorizationWarningNotification($pdo, $student_id, $foundStudent['full_name'], $followupDataForNotify);
            }
            if ($behavior_rating <= 5) {
                generateBehaviorWarningNotification($pdo, $student_id, $foundStudent['full_name'], $followupDataForNotify);
            }

            $success = 'تم حفظ المتابعة بنجاح';
        } catch (PDOException $e) {
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}
?>

<div class="main-content">
    <header class="content-header">
        <h1>إدخال المتابعة اليومية</h1>
        <p class="breadcrumb">لوحة التحكم / إدخال المتابعة</p>
    </header>

    <div class="search-box">
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; flex: 1;">
            <input type="text" name="search" placeholder="البحث عن الطالب بالاسم أو رقم الهوية..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
            <button type="submit" class="btn btn-primary">بحث</button>
        </form>
    </div>

    <?php if (!empty($matchedStudents)): ?>
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>اسم الطالب</th>
                    <th>رقم الهوية</th>
                    <th>جوال ولي الأمر</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matchedStudents as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                    <td><?= htmlspecialchars($s['student_id']) ?></td>
                    <td><?= htmlspecialchars($s['guardian_phone']) ?></td>
                    <td class="actions-cell">
                        <a class="btn btn-sm btn-primary" href="?student_id=<?= (int)$s['id'] ?>&search=<?= urlencode($search) ?>">ادخال متابعة</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ($foundStudent): ?>
    <div class="followup-entry-page">
        <div class="student-search-result">
            <p class="found-student-name"><?= htmlspecialchars($foundStudent['full_name']) ?></p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success" style="background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger" style="background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div class="followup-form-section form-card">
            <form method="POST" action="">
                <input type="hidden" name="student_id" value="<?= (int)$foundStudent['id'] ?>">
                <input type="hidden" name="followup_date" id="followup_date" value="<?= date('Y-m-d') ?>">
                <input type="hidden" name="day_name" id="day_name" value="<?= date('l') ?>">
                <input type="hidden" name="followup_time" id="followup_time" value="<?= date('H:i:s') ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>التاريخ والوقت</label>
                        <input type="text" value="<?= date('Y-m-d H:i') ?>" disabled style="background: #f5f5f5;">
                    </div>
                </div>

                <h4 style="margin: 1.5rem 0 1rem; color: #08462c;">حفظ القرآن</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="memorization_from">حفظ من</label>
                        <input type="text" id="memorization_from" name="memorization_from" placeholder="مثال: سورة البقرة الآية 1" required>
                    </div>
                    <div class="form-group">
                        <label for="memorization_to">حفظ إلى</label>
                        <input type="text" id="memorization_to" name="memorization_to" placeholder="مثال: سورة البقرة الآية 10" required>
                    </div>
                    <div class="form-group">
                        <label for="memorization_rating">تقييم الحفظ (من 5)</label>
                        <select id="memorization_rating" name="memorization_rating">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <h4 style="margin: 1.5rem 0 1rem; color: #08462c;">المراجعة</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="review_from">مراجعة من</label>
                        <input type="text" id="review_from" name="review_from" placeholder="اختياري">
                    </div>
                    <div class="form-group">
                        <label for="review_to">مراجعة إلى</label>
                        <input type="text" id="review_to" name="review_to" placeholder="اختياري">
                    </div>
                    <div class="form-group">
                        <label for="review_rating">تقييم المراجعة (من 5)</label>
                        <select id="review_rating" name="review_rating">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <h4 style="margin: 1.5rem 0 1rem; color: #08462c;">السلوك</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="behavior_rating">تقييم السلوك (من 10)</label>
                        <select id="behavior_rating" name="behavior_rating">
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= $i == 10 ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label for="notes">الملاحظات</label>
                        <textarea id="notes" name="notes" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">حفظ / إرسال</button>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($search): ?>
    <div class="alert alert-warning" style="background: #fff8e1; color: #f57c00; padding: 1rem; border-radius: 8px;">
        لم يتم العثور على طالب بهذا الاسم أو رقم الهوية
    </div>
    <?php else: ?>
    <div class="empty-state" style="text-align: center; padding: 3rem; color: #5D6D5D;">
        <p style="font-size: 1.25rem; margin-bottom: 0.5rem;">ابحث عن طالب لتسجيل متابعته</p>
        <p>أدخل اسم الطالب أو رقم هويته في مربع البحث أعلاه</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
