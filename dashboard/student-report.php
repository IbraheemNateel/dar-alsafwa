<?php
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'تقرير الطالب';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

$search = trim($_GET['search'] ?? '');
$studentId = (int)($_GET['id'] ?? 0);

[$selectedMonth, $selectedYear] = getSelectedMonthYear();
[$monthStart, $monthEnd] = getMonthDateRange($selectedMonth, $selectedYear);

$student = null;
$matches = [];

if ($studentId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
}

if (!$student && $search !== '') {
    $stmt = $pdo->prepare('SELECT id, full_name, student_id FROM students WHERE full_name LIKE ? OR student_id LIKE ? ORDER BY full_name LIMIT 20');
    $stmt->execute(["%$search%", "%$search%"]); 
    $matches = $stmt->fetchAll();

    if (count($matches) === 1) {
        $studentId = (int)$matches[0]['id'];
        $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        $matches = [];
    }
}

$today = date('Y-m-d');

$rank = null;
$totalStudents = null;
$monthlyFollowups = [];
$behaviorWarnings = [];
$memorizationWarnings = [];
$absenceDates = [];

if ($student) {
    $totalStudents = (int)($pdo->query('SELECT COUNT(*) FROM students')->fetchColumn());

    $rankStmt = $pdo->prepare("SELECT s.id,
           COALESCE(AVG((df.memorization_rating/5*100 + df.review_rating/5*100 + df.behavior_rating/10*100)/3), 0) as avg_score
        FROM students s
        LEFT JOIN daily_followup df ON s.id = df.student_id
            AND df.followup_date BETWEEN ? AND ?
        GROUP BY s.id
        ORDER BY avg_score DESC, s.full_name ASC");
    $rankStmt->execute([$monthStart, $monthEnd]);

    $rank = 0;
    while ($row = $rankStmt->fetch()) {
        $rank++;
        if ((int)$row['id'] === (int)$student['id']) {
            break;
        }
    }

    $followStmt = $pdo->prepare('SELECT * FROM daily_followup WHERE student_id = ? AND followup_date BETWEEN ? AND ? ORDER BY followup_date DESC');
    $followStmt->execute([(int)$student['id'], $monthStart, $monthEnd]);
    $monthlyFollowups = $followStmt->fetchAll();

    $behStmt = $pdo->prepare("SELECT followup_date, day_name, behavior_rating
        FROM daily_followup
        WHERE student_id = ?
          AND behavior_rating < 5
          AND followup_date BETWEEN ? AND ?
        ORDER BY followup_date DESC");
    $behStmt->execute([(int)$student['id'], $monthStart, $monthEnd]);
    $behaviorWarnings = $behStmt->fetchAll();

    $memStmt = $pdo->prepare("SELECT followup_date, day_name, memorization_rating, review_rating
        FROM daily_followup
        WHERE student_id = ?
          AND (memorization_rating < 3 OR review_rating < 3)
          AND followup_date BETWEEN ? AND ?
        ORDER BY followup_date DESC");
    $memStmt->execute([(int)$student['id'], $monthStart, $monthEnd]);
    $memorizationWarnings = $memStmt->fetchAll();

    $followupSet = [];
    foreach ($monthlyFollowups as $f) {
        $followupSet[$f['followup_date']] = true;
    }

    $now = new DateTime('now');
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');
    $isCurrentPeriod = ($selectedMonth === $currentMonth && $selectedYear === $currentYear);

    $absenceEndDate = $monthEnd;
    if ($isCurrentPeriod) {
        $todayCutoff = new DateTime('today 21:00');
        $absenceEndDate = $today;
        if ($now < $todayCutoff) {
            $absenceEndDate = date('Y-m-d', strtotime('-1 day'));
        }
    }

    $d = new DateTime($monthStart);
    $end = new DateTime(min($absenceEndDate, $monthEnd));
    
    // استثناء أيام الدروس الجماعية من الغياب
    $bcStmt = $pdo->prepare("SELECT DISTINCT DATE(created_at) as broadcast_date, title, message
                             FROM student_notifications 
                             WHERE student_id = ? AND type = 'broadcast' 
                             AND DATE(created_at) BETWEEN ? AND ?
                             ORDER BY created_at DESC");
    $bcStmt->execute([(int)$student['id'], $monthStart, $monthEnd]);
    $broadcastDays = $bcStmt->fetchAll();
    $broadcastDatesSet = array_column($broadcastDays, 'broadcast_date');
    
    while ($d <= $end) {
        $dateStr = $d->format('Y-m-d');
        $dayOfWeek = (int)$d->format('N');
        if ($dayOfWeek === 5) {
            $d->modify('+1 day');
            continue;
        }
        // يوم الدرس الجماعي لا يُحسب غياب
        if (!isset($followupSet[$dateStr]) && !in_array($dateStr, $broadcastDatesSet)) {
            $absenceDates[] = $dateStr;
        }
        $d->modify('+1 day');
    }
}
?>

<div class="main-content">
    <header class="content-header">
        <h1>تقرير الطالب</h1>
        <p class="breadcrumb">لوحة التحكم / تقرير الطالب</p>
    </header>

    <div class="search-box">
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; flex: 1; align-items: center;">
            <input type="hidden" name="month" value="<?= (int)$selectedMonth ?>">
            <input type="hidden" name="year" value="<?= (int)$selectedYear ?>">
            <input type="text" name="search" placeholder="ابحث بالاسم أو رقم الهوية..." value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
            <button type="submit" class="btn btn-primary">بحث</button>
        </form>

        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; align-items: center;">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php if ($studentId > 0): ?>
                <input type="hidden" name="id" value="<?= (int)$studentId ?>">
            <?php endif; ?>

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

            <button type="submit" class="btn btn-secondary">عرض</button>
        </form>
    </div>

    <?php if (!$student && $search === ''): ?>
        <div class="empty-state">
            <div class="empty-icon">🔎</div>
            <h3>ابحث عن طالب</h3>
            <p>اكتب اسم الطالب أو رقم الهوية لعرض تقريره الكامل</p>
        </div>
    <?php elseif (!$student && !empty($matches)): ?>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>اسم الطالب</th>
                        <th>رقم الهوية</th>
                        <th>اختيار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['full_name']) ?></td>
                            <td><?= htmlspecialchars($m['student_id']) ?></td>
                            <td class="actions-cell">
                                <a class="btn btn-sm btn-primary" href="?id=<?= (int)$m['id'] ?>&month=<?= (int)$selectedMonth ?>&year=<?= (int)$selectedYear ?>">عرض التقرير</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif (!$student): ?>
        <div class="empty-state">
            <div class="empty-icon">❗</div>
            <h3>لم يتم العثور على طالب</h3>
            <p>جرّب كتابة الاسم بشكل مختلف أو استخدم رقم الهوية</p>
        </div>
    <?php else: ?>

        <section class="info-grid">
            <div class="info-card">
                <h3>👤 بيانات الطالب</h3>
                <div class="info-item">
                    <label>الاسم</label>
                    <span><?= htmlspecialchars($student['full_name']) ?></span>
                </div>
                <div class="info-item">
                    <label>رقم الهوية</label>
                    <span><?= htmlspecialchars($student['student_id']) ?></span>
                </div>
                <div class="info-item">
                    <label>تاريخ الميلاد</label>
                    <span><?= htmlspecialchars($student['birth_date']) ?></span>
                </div>
                <div class="info-item">
                    <label>جوال ولي الأمر</label>
                    <span><?= htmlspecialchars($student['guardian_phone']) ?></span>
                </div>
            </div>

            <div class="info-card">
                <h3>🏆 ترتيب الطالب (هذا الشهر)</h3>
                <div class="info-item">
                    <label>الترتيب</label>
                    <span><?= $rank ?> / <?= $totalStudents ?></span>
                </div>
                <div class="info-item">
                    <label>عدد المتابعات (الشهر المختار)</label>
                    <span><?= count($monthlyFollowups) ?></span>
                </div>
                <div class="info-item">
                    <label>عدد أيام الغياب (الشهر المختار)</label>
                    <span><?= count($absenceDates) ?></span>
                </div>
                <div class="info-item">
                    <label>إجمالي إنذارات السلوك (الشهر المختار)</label>
                    <span><?= count($behaviorWarnings) ?></span>
                </div>
                <div class="info-item">
                    <label>إجمالي إنذارات الحفظ/المراجعة (الشهر المختار)</label>
                    <span><?= count($memorizationWarnings) ?></span>
                </div>
            </div>
        </section>

        <section style="margin-top: 1.5rem;">
            <header class="content-header" style="margin-bottom: 1rem;">
                <h2 style="font-size: 1.35rem;">📝 المتابعات (الشهر المختار)</h2>
            </header>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>اليوم</th>
                            <th>التاريخ</th>
                            <th>الحفظ</th>
                            <th>تقييم الحفظ</th>
                            <th>المراجعة</th>
                            <th>تقييم المراجعة</th>
                            <th>السلوك</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyFollowups as $f): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['day_name']) ?></td>
                                <td><?= htmlspecialchars($f['followup_date']) ?></td>
                                <td><?= htmlspecialchars(($f['memorization_from'] ?? '') . ' - ' . ($f['memorization_to'] ?? '')) ?></td>
                                <td><?= (int)$f['memorization_rating'] ?>/5</td>
                                <td><?= htmlspecialchars(($f['review_from'] ?? '') . ' - ' . ($f['review_to'] ?? '')) ?></td>
                                <td><?= (int)$f['review_rating'] ?>/5</td>
                                <td><?= (int)$f['behavior_rating'] ?>/10</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($monthlyFollowups)): ?>
                            <tr><td colspan="7" style="text-align: center; padding: 2rem;">لا توجد متابعات لهذا الشهر</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="margin-top: 1.5rem;">
            <header class="content-header" style="margin-bottom: 1rem;">
                <h2 style="font-size: 1.35rem;">⚠️ إنذارات السلوك (الشهر المختار)</h2>
            </header>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>اليوم</th>
                            <th>التاريخ</th>
                            <th>التقييم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($behaviorWarnings as $w): ?>
                            <tr>
                                <td><?= htmlspecialchars($w['day_name']) ?></td>
                                <td><?= htmlspecialchars($w['followup_date']) ?></td>
                                <td><?= (int)$w['behavior_rating'] ?>/10</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($behaviorWarnings)): ?>
                            <tr><td colspan="3" style="text-align: center; padding: 2rem;">لا توجد إنذارات</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="margin-top: 1.5rem;">
            <header class="content-header" style="margin-bottom: 1rem;">
                <h2 style="font-size: 1.35rem;">📚 إنذارات الحفظ/المراجعة (الشهر المختار)</h2>
            </header>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>اليوم</th>
                            <th>التاريخ</th>
                            <th>تقييم الحفظ</th>
                            <th>تقييم المراجعة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($memorizationWarnings as $w): ?>
                            <tr>
                                <td><?= htmlspecialchars($w['day_name']) ?></td>
                                <td><?= htmlspecialchars($w['followup_date']) ?></td>
                                <td><?= (int)$w['memorization_rating'] ?>/5</td>
                                <td><?= (int)$w['review_rating'] ?>/5</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($memorizationWarnings)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 2rem;">لا توجد إنذارات</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section style="margin-top: 1.5rem;">
            <header class="content-header" style="margin-bottom: 1rem;">
                <h2 style="font-size: 1.35rem;">📅 الغياب (<?= (int)$selectedMonth ?> / <?= (int)$selectedYear ?>)</h2>
            </header>
            <?php if (empty($absenceDates)): ?>
                <div class="empty-state" style="padding: 2rem;">
                    <div class="empty-icon">✅</div>
                    <h3>لا يوجد غياب</h3>
                    <p>لا توجد أيام غياب في هذا الشهر</p>
                </div>
            <?php else: ?>
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>تاريخ الغياب</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absenceDates as $ad): ?>
                                <tr><td><?= htmlspecialchars($ad) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
