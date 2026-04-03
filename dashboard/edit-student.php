<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'تعديل بيانات الطالب';
require_once __DIR__ . '/../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: all-students.php'); exit; }

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { header('Location: all-students.php'); exit; }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $father_id = trim($_POST['father_id'] ?? '');
    $guardian_phone = trim($_POST['guardian_phone'] ?? '');

    if (empty($full_name) || empty($student_id) || empty($birth_date) || empty($father_id) || empty($guardian_phone)) {
        $error = 'يرجى ملء جميع الحقول';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE students SET full_name=?, student_id=?, birth_date=?, father_id=?, guardian_phone=? WHERE id=?");
            $stmt->execute([$full_name, $student_id, $birth_date, $father_id, $guardian_phone, $id]);
            $success = 'تم التحديث بنجاح';
            $student = array_merge($student, $_POST);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $error = 'رقم هوية الطالب مسجل لطالب آخر';
            else $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}
?>

<div class="main-content">
    <header class="content-header">
        <h1>تعديل بيانات الطالب</h1>
        <p class="breadcrumb">لوحة التحكم / تعديل الطالب</p>
    </header>

    <?php if ($success): ?><div class="alert alert-success" style="background:#e8f5e9;color:#2e7d32;padding:1rem;border-radius:8px;margin-bottom:1.5rem;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger" style="background:#ffebee;color:#c62828;padding:1rem;border-radius:8px;margin-bottom:1.5rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">اسم الطالب رباعي</label>
                    <input type="text" id="full_name" name="full_name" required value="<?= htmlspecialchars($student['full_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="student_id">رقم هوية الطالب</label>
                    <input type="text" id="student_id" name="student_id" required value="<?= htmlspecialchars($student['student_id']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="birth_date">تاريخ الميلاد</label>
                    <input type="date" id="birth_date" name="birth_date" required value="<?= htmlspecialchars($student['birth_date']) ?>">
                </div>
                <div class="form-group">
                    <label for="father_id">رقم هوية ولي الأمر</label>
                    <input type="text" id="father_id" name="father_id" required value="<?= htmlspecialchars($student['father_id']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="guardian_phone">رقم جوال ولي الأمر</label>
                    <input type="tel" id="guardian_phone" name="guardian_phone" required value="<?= htmlspecialchars($student['guardian_phone']) ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                <a href="all-students.php" class="btn btn-outline">إلغاء</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
