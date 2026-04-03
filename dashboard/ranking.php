<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'ترتيب الطلبة';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

[$selectedMonth, $selectedYear] = getSelectedMonthYear();
[$monthStart, $monthEnd] = getMonthDateRange($selectedMonth, $selectedYear);

$stmt = $pdo->prepare("
    SELECT s.id, s.full_name,
           COALESCE(AVG((df.memorization_rating/5*100 + df.review_rating/5*100 + df.behavior_rating/10*100)/3), 0) as avg_score
    FROM students s
    LEFT JOIN daily_followup df ON s.id = df.student_id
        AND df.followup_date BETWEEN ? AND ?
    GROUP BY s.id
    ORDER BY avg_score DESC
");
$stmt->execute([$monthStart, $monthEnd]);
$rankedStudents = $stmt->fetchAll();
?>

<div class="main-content">
    <header class="content-header">
        <h1>ترتيب الطلبة</h1>
        <p class="breadcrumb">لوحة التحكم / ترتيب الطلبة</p>
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
            <a class="btn btn-secondary" href="export-ranking-excel.php?month=<?= (int)$selectedMonth ?>&year=<?= (int)$selectedYear ?>">تصدير (Excel)</a>
        </form>
    </div>

    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>الترتيب</th>
                    <th>اسم الطالب</th>
                    <th>التقييم من 100</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rankedStudents as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($s['full_name']) ?></td>
                    <td><?= round($s['avg_score'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
