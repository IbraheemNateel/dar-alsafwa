<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'تسجيل طالب جديد';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/database.php';
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
            $pdo = getConnection();
            $stmt = $pdo->prepare("INSERT INTO students (full_name, student_id, birth_date, father_id, guardian_phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $student_id, $birth_date, $father_id, $guardian_phone]);
            $success = 'تم تسجيل الطالب بنجاح';
            $_POST = [];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'رقم هوية الطالب مسجل مسبقاً';
            } else {
                $error = 'حدث خطأ: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="main-content">
    <header class="content-header">
        <h1>تسجيل طالب جديد</h1>
        <p class="breadcrumb">لوحة التحكم / تسجيل طالب</p>
    </header>

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

    <div class="form-card">
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">اسم الطالب رباعي</label>
                    <input type="text" id="full_name" name="full_name" placeholder="الاسم الكامل" required 
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="student_id">رقم هوية الطالب</label>
                    <input type="text" id="student_id" name="student_id" placeholder="رقم الهوية" required 
                           value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="birth_date">تاريخ الميلاد</label>
                    <input type="date" id="birth_date" name="birth_date" required 
                           value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="father_id">رقم هوية ولي الأمر</label>
                    <input type="text" id="father_id" name="father_id" placeholder="رقم هوية الوالد" required 
                           value="<?= htmlspecialchars($_POST['father_id'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="guardian_phone">رقم جوال ولي الأمر</label>
                    <input type="tel" id="guardian_phone" name="guardian_phone" placeholder="05xxxxxxxx" required 
                           value="<?= htmlspecialchars($_POST['guardian_phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">حفظ</button>
                <button type="reset" class="btn btn-outline">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
