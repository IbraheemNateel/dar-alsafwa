<?php
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'سجل الغياب';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

$absent_students = [];
$beforeCutoff = false;

try {
    $now = new DateTime('now');
    $todayCutoff = new DateTime('today 21:00');
    $beforeCutoff = ($now < $todayCutoff);

    // جلب الطلبة الغائبين اليوم (الذين لم تتم متابعتهم)
    if (!$beforeCutoff) {
        $stmt = $pdo->prepare("SELECT s.*, 
                              COUNT(d.id) as total_followups,
                              MAX(d.followup_date) as last_followup
                              FROM students s 
                              LEFT JOIN daily_followup d ON s.id = d.student_id 
                              WHERE NOT EXISTS (
                                  SELECT 1 FROM daily_followup d2 
                                  WHERE d2.student_id = s.id AND d2.followup_date = CURDATE()
                              )
                              GROUP BY s.id 
                              ORDER BY s.full_name");
        $stmt->execute();
        $absent_students = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log("Error fetching absent students: " . $e->getMessage());
}
?>

<div class="main-content">
    <header class="content-header">
        <h1>سجل الغياب</h1>
        <p class="breadcrumb">لوحة التحكم / الغياب</p>
    </header>

    <?php if ($beforeCutoff): ?>
    <div class="alert alert-info" style="background: #EBF5FB; color: #3498DB; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        لا يتم احتساب الغياب قبل الساعة 9:00 مساءً. سيتم تحديث القائمة تلقائياً بعد الساعة 9 مساءً إذا لم يتم تسجيل متابعة للطالب.
    </div>
    <?php endif; ?>

    <?php if (empty($absent_students)): ?>
    <div class="empty-state">
        <div class="empty-icon">✅</div>
        <h3>لا يوجد غياب اليوم</h3>
        <p>جميع الطلبة حضروا اليوم</p>
    </div>
    <?php else: ?>
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>اسم الطالب</th>
                    <th>رقم الهوية</th>
                    <th>رقم ولي الأمر</th>
                    <th>تاريخ الغياب</th>
                    <th>إجمالي المتابعات</th>
                    <th>آخر متابعة</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($absent_students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                    <td><?= htmlspecialchars($student['student_id']) ?></td>
                    <td><?= htmlspecialchars($student['guardian_phone']) ?></td>
                    <td><?= date('Y-m-d') ?></td>
                    <td><?= $student['total_followups'] ?></td>
                    <td><?= $student['last_followup'] ? date('Y-m-d', strtotime($student['last_followup'])) : 'لا يوجد' ?></td>
                    <td class="actions-cell">
                        <a href="student-page.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-primary">عرض</a>
                        <a href="daily-followup.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-success">متابعة</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>