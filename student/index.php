<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../dashboard/index.php');
    exit;
}

$page_title = 'صفحة الطالب';
require_once __DIR__ . '/../includes/student-header.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

$student_id = $_SESSION['student_id'];

// جلب بيانات الطالب
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// جلب الإشعارات
$stmt = $pdo->prepare("SELECT * FROM student_notifications WHERE student_id = ? ORDER BY created_at DESC");
$stmt->execute([$student_id]);
$notifications = $stmt->fetchAll();

// جلب المتابعات الأخيرة
[$selectedMonth, $selectedYear] = getSelectedMonthYear();
[$monthStart, $monthEnd] = getMonthDateRange($selectedMonth, $selectedYear);

$stmt = $pdo->prepare("SELECT * FROM daily_followup WHERE student_id = ? AND followup_date BETWEEN ? AND ? ORDER BY followup_date DESC");
$stmt->execute([$student_id, $monthStart, $monthEnd]);
$followups = $stmt->fetchAll();

// حساب الغياب
$workingDays = [];
$current = strtotime($monthStart);
// لا يعقل أن يتم حساب الأيام المستقبلية كغياب، نقف عند تاريخ اليوم
$realEnd = min(strtotime($monthEnd), strtotime('today'));
$end = $realEnd;
while ($current <= $end) {
    $dayOfWeek = date('N', $current); // 1 = Monday, 7 = Sunday
    if ($dayOfWeek != 5) { // استثناء الجمعة
        $workingDays[] = date('Y-m-d', $current);
    }
    $current = strtotime('+1 day', $current);
}

$followedDays = array_column($followups, 'followup_date');

// الغيابات الصريحة
$absStmt = $pdo->prepare("SELECT notification_date FROM sent_notifications WHERE student_id = ? AND notification_type = 'absence' AND notification_date BETWEEN ? AND ?");
try {
    $absStmt->execute([$student_id, $monthStart, $monthEnd]);
    $explicitAbsences = array_column($absStmt->fetchAll(), 'notification_date');
} catch (PDOException $e) { $explicitAbsences = []; }

// الأيام المغلقة (دروس جماعية أو اعتماد يومي)
$closedStmt = $pdo->prepare("SELECT closure_date FROM daily_closure_log WHERE closure_date BETWEEN ? AND ?");
try {
    $closedStmt->execute([$monthStart, $monthEnd]);
    $closedDays = array_column($closedStmt->fetchAll(), 'closure_date');
} catch (PDOException $e) { $closedDays = []; }

// استثناء أيام الدروس الجماعية من الغياب (التوافق القديم)
$bcStmt = $pdo->prepare("SELECT DISTINCT DATE(created_at) as broadcast_date 
                         FROM student_notifications 
                         WHERE student_id = ? AND type = 'broadcast' 
                         AND DATE(created_at) BETWEEN ? AND ?");
$bcStmt->execute([$student_id, $monthStart, $monthEnd]);
$broadcastDates = array_column($bcStmt->fetchAll(), 'broadcast_date');

$absentDays = [];
foreach ($workingDays as $day) {
    if (in_array($day, $followedDays)) continue;
    if (in_array($day, $explicitAbsences)) {
        $absentDays[] = $day;
        continue;
    }
    if (in_array($day, $closedDays)) continue;
    if (in_array($day, $broadcastDates)) continue;
    if ($day === date('Y-m-d')) continue;
    
    $absentDays[] = $day;
}

// حساب الترتيب
$rankStmt = $pdo->prepare("
    SELECT COUNT(*) + 1 as rank FROM (
        SELECT s.id,
               COALESCE(AVG((df.memorization_rating/5*100 + df.review_rating/5*100 + df.behavior_rating/10*100)/3), 0) as avg_score
        FROM students s
        LEFT JOIN daily_followup df ON s.id = df.student_id
            AND df.followup_date BETWEEN ? AND ?
        GROUP BY s.id
        HAVING avg_score > (
            SELECT COALESCE(AVG((df2.memorization_rating/5*100 + df2.review_rating/5*100 + df2.behavior_rating/10*100)/3), 0)
            FROM daily_followup df2
            WHERE df2.student_id = ?
            AND df2.followup_date BETWEEN ? AND ?
        )
    ) as higher_students
");
$rankStmt->execute([$monthStart, $monthEnd, $student_id, $monthStart, $monthEnd]);
$rank = $rankStmt->fetch()['rank'];

?>

<style>
    .dashboard-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 2rem;
        align-items: start;
    }
    .btn-export {
        background: #27ae60;
        border-color: #27ae60;
        color: white;
        text-decoration: none;
        padding: 0.6rem 1.25rem;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        transition: transform 0.2s, background 0.2s;
        box-shadow: 0 4px 6px rgba(39, 174, 96, 0.2);
    }
    .btn-export:hover {
        background: #219653;
        transform: translateY(-2px);
        color: white;
    }
    .stat-card {
        border: 1px solid #edf2f7;
    }
    /* Notification Animations */
    .notification-item {
        transition: all 0.2s ease-in-out;
    }
    .notification-item:hover {
        transform: translateY(-2px);
    }
    .dashboard-table th {
        background: #f8f9fa;
        color: #2c3e50;
        font-weight: 700;
        padding: 1rem;
        text-align: right;
    }
    .dashboard-table td {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        color: #4a5568;
    }
    .dashboard-table tbody tr {
        transition: background 0.15s;
    }
    .dashboard-table tbody tr:hover {
        background: #fdfdfd;
    }
    @media (max-width: 992px) {
        .dashboard-layout {
            grid-template-columns: minmax(0, 1fr) !important;
            gap: 1.5rem;
            width: 100%;
        }
        .dashboard-main-col, .dashboard-side-col {
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
        }
        .stats-summary-grid {
            grid-template-columns: minmax(0, 1fr) !important;
        }
        .stats-summary-item {
            border-left: none !important;
            border-bottom: 1px solid #edf2f7;
        }
        .stats-summary-item:last-child {
            border-bottom: none;
        }
        .unread-alert {
            padding: 1.25rem 10px !important;
            margin-bottom: 1.5rem !important;
            flex-direction: row;
            gap: 0.75rem !important;
        }
        .unread-alert div:nth-child(1) {
            font-size: 1.75rem !important;
        }
        .unread-alert h3 {
            font-size: 1.1rem !important;
        }
        .filter-header {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 1rem !important;
            text-align: center !important;
        }
        .filter-form {
            flex-direction: column !important;
            align-items: stretch !important;
            width: 100% !important;
            gap: 0.75rem !important;
        }
        .filter-form .form-group {
            width: 100% !important;
        }
        .filter-form select {
            width: 100% !important;
            min-width: 0 !important;
        }
        .filter-form button {
            width: 100% !important;
        }
        .page-header {
            text-align: center !important;
            flex-direction: column !important;
            padding: 1.5rem 10px !important;
            gap: 1rem !important;
        }
        .header-actions {
            width: 100% !important;
            justify-content: center !important;
        }
        .btn-export {
            width: 100% !important;
            justify-content: center !important;
        }
        .stats-grid {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }
        .stats-summary-grid {
            display: flex !important;
            flex-direction: column !important;
            width: 100% !important;
        }
        .notification-item .notif-title-row {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 0.2rem;
        }
        .identity-item {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 0.5rem !important;
        }
        .identity-item strong {
            width: 100%;
            text-align: right !important;
        }
        /* مركزة عناصر الهيدر (جملة الترحيب والشعار) */
        .student-header-content {
            justify-content: center !important;
            text-align: center;
        }
        .student-header-content > div {
            justify-content: center !important;
            width: 100% !important;
        }
        .student-info { margin: 0 auto; justify-content: center !important; }
        
        /* ضبط الأقسام (الهوية، الإشعارات، الخ) لتطابق نفس العرض تماماً وتعالج تجاوز المحتوى */
        .content-section, .stat-card, .stats-summary-grid {
            max-width: 100% !important;
            width: 100% !important;
            box-sizing: border-box !important;
            overflow: hidden !important; 
        }
        /* السماح بكسر أي كلمة طويلة في الإشعارات والهوية */
        .notifications-list *, .identity-item * {
            word-break: break-word !important;
            white-space: normal !important;
            max-width: 100% !important;
        }
        .notif-date { white-space: nowrap !important; }
    }
    .dashboard-main-col { order: 2; display: flex; flex-direction: column; gap: 2rem; }
    .dashboard-side-col { order: 1; display: flex; flex-direction: column; gap: 2rem; }
    .stats-summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); background: #f8fafc; border-bottom: 1px solid #edf2f7; }
    .stats-summary-item { text-align: center; padding: 1.25rem; border-left: 1px solid #edf2f7; }
    .stats-summary-item:last-child { border-left: none; }
</style>

<main class="student-main-content">
    <div class="container">
        <!-- Dashboard Header -->
        <div class="page-header" style="padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; border-right: 4px solid #3498db; text-align: right; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.75rem; color: #2C3E50; margin-bottom: 0.25rem;">مرحباً بك، <?= htmlspecialchars($student['full_name']) ?> 👋</h1>
                <p style="margin: 0; color: #7f8c8d; font-size: 1rem;">لوحة تحكم الطالب | استمتع بمتابعة تقدمك</p>
            </div>
            <div class="header-actions">
                <a href="export.php?month=<?= (int)$selectedMonth ?>&year=<?= (int)$selectedYear ?>" class="btn-export">
                    📥 تصدير التقرير كـ Excel
                </a>
            </div>
        </div>

        <!-- إشعارات هامة للمستخدم (تظهر في الأعلى بشكل بارز إن وجدت) -->
        <?php 
        $unreadCount = count(array_filter($notifications, fn($n) => !$n['read_at']));
        if ($unreadCount > 0): 
        ?>
        <div class="unread-alert" style="background: linear-gradient(to left, #fff9e6, #fffde7); color: #856404; border: 1px solid #ffeeba; display: flex; align-items: center; gap: 1rem; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(241, 196, 15, 0.1); border-right: 4px solid #f1c40f;">
            <div style="font-size: 2.5rem; filter: drop-shadow(0 2px 4px rgba(241,196,15,0.4)); animation: pulse 2s infinite;">🔔</div>
            <div>
                <h3 style="margin: 0 0 0.25rem 0; color: #b78a05; font-size: 1.25rem; font-weight: 700;">يوجد لديك <?= $unreadCount ?> إشعار هام لم تقرأه بعد!</h3>
                <p style="margin: 0; font-size: 0.95rem; color: #856404;">يرجى مراجعة قسم الإشعارات في الأسفل للاطلاع على التحديثات.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- الإحصاءات السريعة -->
        <div class="stats-grid">
            <div class="stat-card" style="box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: white;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f1c40f, #f39c12); box-shadow: 0 4px 10px rgba(241, 196, 15, 0.3);">🏆</div>
                <div class="stat-content">
                    <h3 style="color: #7f8c8d; font-size: 1rem; font-weight: 600; margin-bottom: 0.2rem;">ترتيب الطالب لهذا الشهر  </h3>
                    <p class="stat-number">#<?= $rank ?></p>
                </div>
            </div>
            <div class="stat-card" style="box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: white;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #2ecc71, #27ae60); box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);">📝</div>
                <div class="stat-content">
                    <h3 style="color: #7f8c8d; font-size: 1rem; font-weight: 600; margin-bottom: 0.2rem;">متابعات الشهر</h3>
                    <p class="stat-number"><?= count($followups) ?></p>
                </div>
            </div>
            <div class="stat-card" style="box-shadow: 0 4px 15px rgba(0,0,0,0.03); background: white;">
                <div class="stat-icon" style="background: linear-gradient(135deg, #e74c3c, #c0392b); box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);">⚠️</div>
                <div class="stat-content">
                    <h3 style="color: #7f8c8d; font-size: 1rem; font-weight: 600; margin-bottom: 0.2rem;">أيام الغياب</h3>
                    <p class="stat-number" style="color: #e74c3c;"><?= count($absentDays) ?></p>
                </div>
            </div>
        </div>

        <!-- فلتر الشهر -->
        <section class="content-section" style="margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #edf2f7; padding: 1.5rem; background: #fff; border-radius: 12px;">
            <div class="filter-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.15rem; color: #2c3e50;"><span style="font-size: 1.5rem;">📅</span> تحديد فترة العرض</h2>
                <form method="GET" action="" class="filter-form" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin: 0;">
                    <div class="form-group" style="margin: 0;">
                        <label for="month" style="font-size: 0.9rem; color: #4a5568; font-weight: 600; display: block; margin-bottom: 0.5rem;">الشهر</label>
                        <select name="month" id="month" style="padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e0; min-width: 130px; outline: none; background: #f8fafc; color: #2d3748; font-family: inherit; font-size: 1rem;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === (int)$selectedMonth ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label for="year" style="font-size: 0.9rem; color: #4a5568; font-weight: 600; display: block; margin-bottom: 0.5rem;">السنة</label>
                        <select name="year" id="year" style="padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #cbd5e0; min-width: 100px; outline: none; background: #f8fafc; color: #2d3748; font-family: inherit; font-size: 1rem;">
                            <?php $yNow = (int)date('Y'); ?>
                            <?php for ($y = $yNow; $y >= 2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === (int)$selectedYear ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; border: none; background: #3498db; color: #fff; cursor: pointer; transition: background 0.2s; font-weight: 600; font-size: 1rem;">تحديث البيانات</button>
                </form>
            </div>
        </section>

        <!-- تقسيم الشاشة -->
        <div class="dashboard-layout">
            
            <!-- العمود الأيمن (البيانات الرئيسية) أصبح ترتيبه الثاني بناء على طلب النقل -->
            <div class="dashboard-main-col">
                
                <!-- المتابعات الشهرية -->
                <section class="content-section" style="box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 0; overflow: hidden; border-radius: 12px; border: none;">
                    <div style="padding: 1.5rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #fff;">
                        <h2 style="margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.5rem;"><span style="font-size: 1.25rem;">📊</span> سجل المتابعات اليومي</h2>
                        <span style="background: #e1f0fa; color: #2b6cb0; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.9rem; font-weight: 700;"><?= count($followups) ?> أيام مسجلة</span>
                    </div>
                    
                    <?php if (empty($followups)): ?>
                        <div style="text-align: center; padding: 4rem 2rem; color: #a0aec0; background: #fafbfc;">
                            <div style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.5;">📝</div>
                            <h3 style="color: #718096; margin-bottom: 0.5rem;">لا توجد متابعات</h3>
                            <p style="margin: 0;">لم يتم إدخال أي تقييم أو متابعة لك خلال هذا الشهر حتى الآن.</p>
                        </div>
                    <?php else: ?>
                        <!-- إحصائيات المتوسطات -->
                        <div class="stats-summary-grid">
                            <div class="stats-summary-item">
                                <div style="font-size: 0.9rem; color: #718096; font-weight: 600; margin-bottom: 0.4rem;">معدل الحفظ</div>
                                <div style="font-weight: 800; font-size: 1.75rem; color: #2c3e50;"><?= number_format(array_sum(array_column($followups, 'memorization_rating')) / count($followups), 1) ?> <span style="font-size: 1rem; color: #a0aec0; font-weight: normal;">/ 5</span></div>
                            </div>
                            <div class="stats-summary-item">
                                <div style="font-size: 0.9rem; color: #718096; font-weight: 600; margin-bottom: 0.4rem;">معدل المراجعة</div>
                                <div style="font-weight: 800; font-size: 1.75rem; color: #2c3e50;"><?= number_format(array_sum(array_column($followups, 'review_rating')) / count($followups), 1) ?> <span style="font-size: 1rem; color: #a0aec0; font-weight: normal;">/ 5</span></div>
                            </div>
                            <div class="stats-summary-item">
                                <div style="font-size: 0.9rem; color: #718096; font-weight: 600; margin-bottom: 0.4rem;">معدل السلوك</div>
                                <div style="font-weight: 800; font-size: 1.75rem; color: #2c3e50;"><?= number_format(array_sum(array_column($followups, 'behavior_rating')) / count($followups), 1) ?> <span style="font-size: 1rem; color: #a0aec0; font-weight: normal;">/ 10</span></div>
                            </div>
                        </div>

                        <div class="data-table-wrapper" style="box-shadow: none; border-radius: 0; margin: 0; overflow-x: auto; max-height: 500px; overflow-y: auto;">
                            <table class="dashboard-table" style="width: 100%; border-collapse: collapse;">
                                <thead style="position: sticky; top: 0; z-index: 10;">
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>نطاق الحفظ</th>
                                        <th style="text-align: center;">تقييم حفظ</th>
                                        <th>نطاق المراجعة</th>
                                        <th style="text-align: center;">تقييم مراجعة</th>
                                        <th style="text-align: center;">سلوك</th>
                                        <th>ملاحظات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($followups as $followup): ?>
                                        <tr>
                                            <td style="font-weight: 600; font-size: 0.95rem;"><?= date('m/d', strtotime($followup['followup_date'])) ?></td>
                                            <td style="font-size: 0.9rem;">
                                                <?php if ($followup['memorization_from']): ?>
                                                    <?= htmlspecialchars($followup['memorization_from']) ?> <br/> <small style="color:#a0aec0;">إلى</small> <?= htmlspecialchars($followup['memorization_to']) ?>
                                                <?php else: ?>-<?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="display: inline-block; padding: 0.3rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; background: <?= $followup['memorization_rating'] >= 4 ? '#e6f4ea' : '#fce8e6' ?>; color: <?= $followup['memorization_rating'] >= 4 ? '#137333' : '#c5221f' ?>;">
                                                    <?= $followup['memorization_rating'] ?>/5
                                                </span>
                                            </td>
                                            <td style="font-size: 0.9rem;">
                                                <?php if ($followup['review_from']): ?>
                                                    <?= htmlspecialchars($followup['review_from']) ?> <br/> <small style="color:#a0aec0;">إلى</small> <?= htmlspecialchars($followup['review_to']) ?>
                                                <?php else: ?>-<?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="display: inline-block; padding: 0.3rem 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; background: <?= $followup['review_rating'] >= 4 ? '#e8f0fe' : '#fce8e6' ?>; color: <?= $followup['review_rating'] >= 4 ? '#1967d2' : '#c5221f' ?>;">
                                                    <?= $followup['review_rating'] ?>/5
                                                </span>
                                            </td>
                                            <td style="text-align: center; font-weight: 800; color: #2c3e50; font-size: 1rem;">
                                                <?= $followup['behavior_rating'] ?>/10
                                            </td>
                                            <td style="font-size: 0.85rem; color: #718096; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($followup['notes']) ?>">
                                                <?= $followup['notes'] ?: '-' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                
                <!-- سجل الغياب -->
                <?php if (!empty($absentDays)): ?>
                <section class="content-section" style="border: 1px solid #fed7d7; box-shadow: 0 4px 20px rgba(229, 62, 62, 0.08); background: #fff; border-radius: 12px; padding: 1.5rem;">
                    <h2 style="color: #c53030; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;"><span style="font-size: 1.25rem;">⚠️</span> التغيب عن الدوام</h2>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <?php foreach ($absentDays as $day): ?>
                            <div style="background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.05); font-size: 0.95rem;">
                                <?= date('Y / m / d', strtotime($day)) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

            </div>

            <!-- العمود الأيسر (الإشعارات والملف الشخصي) أصبح ترتيبه الأول بناء على طلب النقل -->
            <div class="dashboard-side-col">
                
                <!-- الإشعارات المتميزة -->
                <section class="content-section" style="background: #fff; padding: 0; overflow: hidden; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                    <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #2c3e50;">
                        <h2 style="margin: 0; color: #ffffff; font-size: 1.15rem; display: flex; align-items: center; gap: 0.5rem;"><span style="font-size: 1.25rem;">📬</span> الإشعارات</h2>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notif-badge" style="background: #e53e3e; color: white; padding: 0.2rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"><?= $unreadCount ?> غير مقروء</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($notifications)): ?>
                        <div style="text-align: center; padding: 3rem 1.5rem; color: #a0aec0; background: #fdfdfd;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.4;">📭</div>
                            <p style="margin: 0; font-size: 0.95rem;">ليس لديك أي إشعارات حالياً</p>
                        </div>
                    <?php else: ?>
                        <div class="notifications-list" style="max-height: 500px; overflow-y: auto; padding: 1rem; background: #fafbfc;">
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item <?= $notif['read_at'] ? 'read' : 'unread' ?>" 
                                     onclick="openNotification(<?= $notif['id'] ?>, this)"
                                     style="border-radius: 10px; padding: 1rem; margin-bottom: 0.75rem; background: <?= $notif['read_at'] ? '#ffffff' : '#ebf8ff' ?>; border: 1px solid <?= $notif['read_at'] ? '#edf2f7' : '#90cdf4' ?>; cursor: pointer; position: relative; overflow: hidden; box-shadow: <?= $notif['read_at'] ? '0 1px 2px rgba(0,0,0,0.02)' : '0 2px 8px rgba(66, 153, 225, 0.15)' ?>; transition: all 0.2s;">
                                    <?php if (!$notif['read_at']): ?>
                                        <div style="position: absolute; right: 0; top: 0; bottom: 0; width: 4px; background: #3182ce;"></div>
                                    <?php endif; ?>
                                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                        <div class="notif-icon-wrapper" style="font-size: 1.5rem; line-height: 1; padding-top: 0.2rem; filter: <?= $notif['read_at'] ? 'grayscale(100%) opacity(70%)' : 'none' ?>;">
                                            <?php
                                            $icon = '💬';
                                            if ($notif['type'] === 'absence') $icon = '⚠️';
                                            elseif ($notif['type'] === 'warning_behavior') $icon = '🚫';
                                            elseif ($notif['type'] === 'warning_memorization') $icon = '📉';
                                            elseif ($notif['type'] === 'broadcast') $icon = '📢';
                                            echo $icon;
                                            ?>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div class="notif-title-row" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.4rem;">
                                                <h4 class="notif-title" style="margin: 0; font-size: 0.95rem; color: <?= $notif['read_at'] ? '#4a5568' : '#2b6cb0' ?>; font-weight: <?= $notif['read_at'] ? '600' : '800' ?>; line-height: 1.4; word-break: break-word;"><?= htmlspecialchars($notif['title']) ?></h4>
                                                <small class="notif-date" style="color: #a0aec0; font-size: 0.75rem; white-space: nowrap; margin-right: 0.5rem;"><?= date('Y/m/d', strtotime($notif['created_at'])) ?></small>
                                            </div>
                                            <?php $shortMsg = mb_substr($notif['message'], 0, 70) . (mb_strlen($notif['message']) > 70 ? '...' : ''); ?>
                                            <p style="margin: 0; font-size: 0.85rem; color: #718096; line-height: 1.6; word-break: break-word;"><?= nl2br(htmlspecialchars($shortMsg)) ?></p>
                                            <div class="full-message" style="display:none;"><?= nl2br(htmlspecialchars($notif['message'])) ?></div>
                                            <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #3182ce; font-weight: 600;">اختر للعرض الكامل 👆</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- معلوماتي الشخصية -->
                <section class="content-section" style="background: #fff; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #edf2f7;">
                    <h2 style="margin-bottom: 1.5rem; font-size: 1.15rem; color: #2d3748; display: flex; align-items: center; gap: 0.5rem; border-bottom: 2px solid #edf2f7; padding-bottom: 0.75rem;"><span style="font-size: 1.25rem;">📋</span> معلومات هويتي</h2>
                    <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem;">
                        <li class="identity-item" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.75rem;">
                            <span style="color: #718096; font-size: 0.9rem;">الاسم الكامل</span>
                            <strong style="color: #2d3748; font-size: 0.95rem; font-weight: 700; word-break: break-word; text-align: left;"><?= htmlspecialchars($student['full_name']) ?></strong>
                        </li>
                        <li class="identity-item" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.75rem;">
                            <span style="color: #718096; font-size: 0.9rem;">رقم الهوية</span>
                            <strong style="color: #3182ce; font-size: 0.95rem; font-weight: 800;"><?= htmlspecialchars($student['student_id']) ?></strong>
                        </li>
                        <li class="identity-item" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.75rem;">
                            <span style="color: #718096; font-size: 0.9rem;">تاريخ الميلاد</span>
                            <strong style="color: #2d3748; font-size: 0.95rem; font-weight: 700;"><?= date('Y-m-d', strtotime($student['birth_date'])) ?></strong>
                        </li>
                        <li class="identity-item" style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #718096; font-size: 0.9rem;">جوال ولي الأمر</span>
                            <strong style="color: #2d3748; font-size: 0.95rem; font-weight: 700;"><?= htmlspecialchars($student['guardian_phone']) ?></strong>
                        </li>
                    </ul>
                </section>

            </div>
        </div>
    </div>
</main>

<!-- نافذة الإشعار المنبثقة -->
<div id="notificationModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; animation: fadeInUp 0.3s ease;">
        <div style="padding: 1.5rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span id="notifModalIcon" style="font-size: 1.75rem;">💬</span>
                <h3 id="notifModalTitle" style="margin: 0; color: #2d3748; font-size: 1.25rem; font-weight: 700;">عنوان الإشعار</h3>
            </div>
            <button onclick="closeNotificationModal()" style="background: none; border: none; font-size: 1.75rem; color: #a0aec0; cursor: pointer; transition: color 0.2s; line-height: 1;">&times;</button>
        </div>
        <div style="padding: 1.5rem;">
            <div style="margin-bottom: 1.25rem; color: #718096; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.1rem;">📅</span>
                <span id="notifModalDate" style="font-weight: 600;"></span>
            </div>
            <div id="notifModalMessage" style="margin: 0; color: #4a5568; line-height: 1.8; font-size: 1.05rem;">
                <!-- Full message here -->
            </div>
        </div>
        <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #edf2f7; text-align: left;">
            <button onclick="closeNotificationModal()" class="btn btn-primary" style="padding: 0.5rem 1.5rem; border-radius: 8px;">إغلاق</button>
        </div>
    </div>
</div>

<script>
function openNotification(id, element) {
    // جلب البيانات من العنصر
    const icon = element.querySelector('.notif-icon-wrapper').innerText.trim();
    const title = element.querySelector('.notif-title').innerText.trim();
    const date = element.querySelector('.notif-date').innerText.trim();
    const fullMessage = element.querySelector('.full-message').innerHTML;

    // تعبئة النافذة المنبثقة
    document.getElementById('notifModalIcon').innerText = icon;
    document.getElementById('notifModalTitle').innerText = title;
    document.getElementById('notifModalDate').innerText = date;
    document.getElementById('notifModalMessage').innerHTML = fullMessage;

    // إظهار النافذة
    document.getElementById('notificationModal').style.display = 'flex';

    // تحديث الحالة كمقروء
    markAsRead(id, element);
}

function closeNotificationModal() {
    document.getElementById('notificationModal').style.display = 'none';
}

function markAsRead(notificationId, element) {
    if (element.classList.contains('read')) return;

    fetch('mark-read.php?id=' + notificationId, { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove visual unread indicators
                element.classList.remove('unread');
                element.classList.add('read');
                element.style.background = '#ffffff';
                element.style.borderColor = '#edf2f7';
                element.style.boxShadow = '0 1px 2px rgba(0,0,0,0.02)';
                
                // Hide side colored bar
                let bar = element.querySelector('div[style*="position: absolute"]');
                if (bar) bar.style.display = 'none';
                
                // Grayscale the icon
                let iconWrapper = element.querySelector('.notif-icon-wrapper');
                if (iconWrapper) {
                    iconWrapper.style.filter = 'grayscale(100%) opacity(70%)';
                }

                // Unbold title and change color
                let title = element.querySelector('.notif-title');
                if(title) {
                    title.style.color = '#4a5568';
                    title.style.fontWeight = '600';
                }

                // Update badge if exists
                const badge = document.querySelector('.notif-badge');
                let currentCount = 0;
                if (badge) {
                    let countMatch = badge.textContent.match(/\d+/);
                    if (countMatch) {
                        currentCount = parseInt(countMatch[0]);
                        if (currentCount > 1) {
                            badge.textContent = (currentCount - 1) + ' غير مقروء';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }

                // Update top alert box if exists
                const alertBox = document.querySelector('.unread-alert');
                if (alertBox) {
                    const alertTitle = alertBox.querySelector('h3');
                    if (alertTitle) {
                        // If we didn't get the count from the badge, try from the title
                        if (currentCount === 0) {
                            let countMatch = alertTitle.textContent.match(/\d+/);
                            if (countMatch) currentCount = parseInt(countMatch[0]);
                        }
                        
                        if (currentCount > 1) {
                            alertTitle.textContent = `يوجد لديك ${currentCount - 1} إشعار هام لم تقرأه بعد!`;
                        } else {
                            alertBox.style.display = 'none';
                        }
                    }
                }
            }
        });
}
</script>

<?php require_once __DIR__ . '/../includes/student-footer.php'; ?>