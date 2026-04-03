<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

$student_id = $_GET['id'] ?? 0;
$student = null;
$daily_followups = [];

if ($student_id > 0) {
    try {
        // جلب بيانات الطالب
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if ($student) {
            // جلب المتابعة اليومية للطالب
            $stmt = $pdo->prepare("SELECT * FROM daily_followup 
                                  WHERE student_id = ? 
                                  ORDER BY followup_date DESC 
                                  LIMIT 30");
            $stmt->execute([$student_id]);
            $daily_followups = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error fetching student data: " . $e->getMessage());
    }
}

if (!$student) {
    header('Location: all-students.php');
    exit;
}
?>

<?php
$page_title = $student['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="main-content">
    <header class="page-header">
        <h1><?= htmlspecialchars($student['full_name']) ?></h1>
        <div class="header-actions">
            <a href="edit-student.php?id=<?= $student['id'] ?>" class="btn btn-primary">تعديل البيانات</a>
            <a href="daily-followup.php?student_id=<?= $student['id'] ?>" class="btn btn-success">متابعة يومية</a>
        </div>
    </header>

    <section class="student-info">
        <div class="info-grid">
            <div class="info-card">
                <h3>المعلومات الشخصية</h3>
                <div class="info-item">
                    <label>الاسم الكامل:</label>
                    <span><?= htmlspecialchars($student['full_name']) ?></span>
                </div>
                <div class="info-item">
                    <label>رقم الهوية:</label>
                    <span><?= htmlspecialchars($student['student_id']) ?></span>
                </div>
                <div class="info-item">
                    <label>تاريخ الميلاد:</label>
                    <span><?= date('Y-m-d', strtotime($student['birth_date'])) ?></span>
                </div>
            </div>

            <div class="info-card">
                <h3>بيانات الاتصال</h3>
                <div class="info-item">
                    <label>رقم هوية الأب:</label>
                    <span><?= htmlspecialchars($student['father_id']) ?></span>
                </div>
                <div class="info-item">
                    <label>رقم ولي الأمر:</label>
                    <span><?= htmlspecialchars($student['guardian_phone']) ?></span>
                </div>
                <div class="info-item">
                    <label>تاريخ التسجيل:</label>
                    <span><?= date('Y-m-d', strtotime($student['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="followup-section">
        <h2>سجل المتابعة اليومية</h2>

        <?php if (empty($daily_followups)): ?>
        <div class="empty-state">
            <div class="empty-icon">📝</div>
            <h3>لا توجد متابعات بعد</h3>
            <p>ابدأ بإضافة متابعة يومية للطالب</p>
            <a href="daily-followup.php?student_id=<?= $student['id'] ?>" class="btn btn-primary">إضافة متابعة</a>
        </div>
        <?php else: ?>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الحفظ</th>
                        <th>تقييم الحفظ</th>
                        <th>المراجعة</th>
                        <th>تقييم المراجعة</th>
                        <th>السلوك</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daily_followups as $followup): ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($followup['followup_date'])) ?></td>
                        <td><?= htmlspecialchars($followup['memorization_from'] ?? '') ?> - <?= htmlspecialchars($followup['memorization_to'] ?? '') ?></td>
                        <td>
                            <span class="rating rating-<?= $followup['memorization_rating'] ?>">
                                <?= $followup['memorization_rating'] ?>/5
                            </span>
                        </td>
                        <td><?= htmlspecialchars($followup['review_from'] ?? '') ?> - <?= htmlspecialchars($followup['review_to'] ?? '') ?></td>
                        <td>
                            <span class="rating rating-<?= $followup['review_rating'] ?>">
                                <?= $followup['review_rating'] ?>/5
                            </span>
                        </td>
                        <td>
                            <span class="rating rating-<?= $followup['behavior_rating'] ?>">
                                <?= $followup['behavior_rating'] ?>/5
                            </span>
                        </td>
                        <td><?= htmlspecialchars($followup['notes'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>