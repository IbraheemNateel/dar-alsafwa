<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'عرض بيانات الطلبة';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

$search = trim($_GET['search'] ?? '');
$students = [];

if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE full_name LIKE ? OR student_id LIKE ? ORDER BY full_name");
    $stmt->execute(["%$search%", "%$search%"]);
    $students = $stmt->fetchAll();
} else {
    $students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll();
}
?>

<div class="main-content">
    <header class="content-header">
        <h1>عرض بيانات الطلبة</h1>
        <p class="breadcrumb">لوحة التحكم / عرض الطلبة</p>
    </header>

    <div class="search-box">
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; flex: 1;">
            <input type="text" name="search" placeholder="البحث بالاسم أو رقم الهوية..." 
                   value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
            <button type="submit" class="btn btn-primary">بحث</button>
        </form>
    </div>

    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>رقم الهوية</th>
                    <th>تاريخ الميلاد</th>
                    <th>رقم هوية الوالد</th>
                    <th>جوال ولي الأمر</th>
                    <th>العمليات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                    <td><?= htmlspecialchars($s['student_id']) ?></td>
                    <td><?= htmlspecialchars($s['birth_date']) ?></td>
                    <td><?= htmlspecialchars($s['father_id']) ?></td>
                    <td><?= htmlspecialchars($s['guardian_phone']) ?></td>
                    <td class="actions-cell">
                        <a href="export-student.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary" title="تصدير إنجاز الطالب">📥</a>
                        <a href="edit-student.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-secondary" title="تعديل">✏️</a>
                        <a href="delete-student.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" title="حذف" 
                           onclick="return confirm('هل أنت متأكد من الحذف؟')">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="export-buttons">
        <a href="export-students-excel.php" class="btn btn-primary">📥 تصدير بيانات الطلبة (Excel)</a>
        <a href="export-ranking-excel.php" class="btn btn-secondary">📥 تصدير ترتيب الطلبة (Excel)</a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
