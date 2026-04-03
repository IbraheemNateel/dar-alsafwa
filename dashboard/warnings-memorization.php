<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'انذارات ضعف الحفظ والمراجعة';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

[$selectedMonth, $selectedYear] = getSelectedMonthYear();
[$monthStart, $monthEnd] = getMonthDateRange($selectedMonth, $selectedYear);

$stmt = $pdo->prepare("
    SELECT s.full_name, df.followup_date, df.day_name, df.memorization_rating, df.review_rating
    FROM students s
    JOIN daily_followup df ON s.id = df.student_id
    WHERE (df.memorization_rating < 3 OR df.review_rating < 3)
      AND df.followup_date BETWEEN ? AND ?
    ORDER BY df.followup_date DESC
");
$stmt->execute([$monthStart, $monthEnd]);
$warnings = $stmt->fetchAll();
?>

<div class="main-content">
    <header class="content-header">
        <h1>انذارات ضعف الحفظ والمراجعة</h1>
        <p class="breadcrumb">لوحة التحكم / انذارات الحفظ والمراجعة</p>
    </header>

    <div class="search-box">
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <select name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === (int)$selectedMonth ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>

            <select name="year">
                <?php $yNow = (int)date('Y'); ?>
                <?php for ($y = $yNow; $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === (int)$selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>

            <button type="submit" class="btn btn-primary">عرض</button>
        </form>
    </div>

    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>اسم الطالب</th>
                    <th>اليوم</th>
                    <th>التاريخ</th>
                    <th>تقييم الحفظ</th>
                    <th>تقييم المراجعة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($warnings as $w): ?>
                <tr>
                    <td><?= htmlspecialchars($w['full_name']) ?></td>
                    <td><?= htmlspecialchars($w['day_name']) ?></td>
                    <td><?= htmlspecialchars($w['followup_date']) ?></td>
                    <td><?= $w['memorization_rating'] ?>/5</td>
                    <td><?= $w['review_rating'] ?>/5</td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($warnings)): ?>
                <tr><td colspan="5" style="text-align: center; padding: 2rem;">لا توجد انذارات</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
