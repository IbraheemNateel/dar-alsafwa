<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

[$selectedMonth, $selectedYear] = getSelectedMonthYear();
[$monthStart, $monthEnd] = getMonthDateRange($selectedMonth, $selectedYear);

// جلب الإحصائيات

$total_students = 0;
$today_followups = 0;
$absent_today = 0;
$behavior_warnings = 0;
$memorization_warnings = 0;

try {
    // إجمالي الطلبة
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $total_students = $stmt->fetch()['count'];
    
    // المتابعات اليومية
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM daily_followup WHERE followup_date = CURDATE()");
    $stmt->execute();
    $today_followups = $stmt->fetch()['count'];
    
    // الغياب اليومي (الطلبة الذين لم تتم متابعتهم اليوم)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students s LEFT JOIN daily_followup d ON s.id = d.student_id AND d.followup_date = CURDATE() WHERE d.id IS NULL");
    $stmt->execute();
    $absent_today = $stmt->fetch()['count'];
    
    // انذارات السلوك
    $stmt = $pdo->query("SELECT COUNT(DISTINCT s.id) as count FROM students s 
                         JOIN daily_followup d ON s.id = d.student_id 
                         WHERE d.followup_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                         AND d.behavior_rating <= 2");
    $stmt->execute();
    $behavior_warnings = $stmt->fetch()['count'];
    
    // انذارات ضعف الحفظ
    $stmt = $pdo->query("SELECT COUNT(DISTINCT s.id) as count FROM students s 
                         JOIN daily_followup d ON s.id = d.student_id 
                         WHERE d.followup_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                         AND (d.memorization_rating <= 2 OR d.review_rating <= 2)");
    $stmt->execute();
    $memorization_warnings = $stmt->fetch()['count'];

    // أفضل 3 للشهر المختار (من 100)
    $topStmt = $pdo->prepare("SELECT s.id, s.full_name,
           COALESCE(AVG((df.memorization_rating/5*100 + df.review_rating/5*100 + df.behavior_rating/10*100)/3), 0) as monthly_total_100
        FROM students s
        LEFT JOIN daily_followup df ON s.id = df.student_id
            AND df.followup_date BETWEEN ? AND ?
        GROUP BY s.id
        ORDER BY monthly_total_100 DESC
        LIMIT 3");
    $topStmt->execute([$monthStart, $monthEnd]);
    $top3 = $topStmt->fetchAll();

    $avgStmt = $pdo->prepare("SELECT COALESCE(AVG((df.memorization_rating/5*100 + df.review_rating/5*100 + df.behavior_rating/10*100)/3), 0) as circle_avg_100
        FROM daily_followup df
        WHERE df.followup_date BETWEEN ? AND ?");
    $avgStmt->execute([$monthStart, $monthEnd]);
    $circle_avg_100 = (float)($avgStmt->fetch()['circle_avg_100'] ?? 0);
    
    // إنشاء جدول الإغلاق اليومي إن لم يكن موجوداً
    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_closure_log (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        closure_date DATE UNIQUE, 
        closed_by_user_id INT, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // التحقق مما إذا كان يوم التسميع الحالي قد تم إغلاقه
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_closure_log WHERE closure_date = CURDATE()");
    $stmt->execute();
    $is_day_closed = $stmt->fetchColumn() > 0;
    
} catch (Exception $e) {
    error_log("Error fetching dashboard stats: " . $e->getMessage());
    $top3 = [];
    $circle_avg_100 = 0;
    $is_day_closed = false;
}
?>
<?php
$page_title = 'لوحة التحكم';
require_once __DIR__ . '/../includes/header.php';
?>

<main class="main-content">
    <header class="page-header">
        <h1>لوحة التحكم</h1>
        <p class="welcome">مرحباً بك يا <?= htmlspecialchars($_SESSION['full_name']) ?></p>
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
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" style="background: #e8f5e9; color: #2e7d32; padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #c8e6c9;">
            <strong style="font-size: 1.1rem;">✅ <?= htmlspecialchars($_SESSION['success_message']) ?></strong>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" style="background: #ffebee; color: #c62828; padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem; border: 1px solid #ffcdd2;">
            <strong style="font-size: 1.1rem;">❌ <?= htmlspecialchars($_SESSION['error_message']) ?></strong>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

    <!-- مربع التحكم باعتماد الغياب (الزر اليدوي) -->
    <div style="background: <?= $is_day_closed ? '#f8fdf8' : '#fff5f5' ?>; border: 1px solid <?= $is_day_closed ? '#c6f6d5' : '#fed7d7' ?>; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="margin: 0 0 0.5rem 0; color: <?= $is_day_closed ? '#276749' : '#c53030' ?>; display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 1.5rem;"><?= $is_day_closed ? '✅' : '⚠️' ?></span> 
                <?= $is_day_closed ? 'تم إغلاق اليوم واعتماد الغيابات' : 'اعتماد الغياب وإغلاق اليوم' ?>
            </h2>
            <p style="margin: 0; color: #718096; font-size: 0.95rem;">
                <?= $is_day_closed 
                    ? 'لقد قمت باعتماد الغيابات لهذا اليوم بنجاح، وتم إشعار جميع الطلبة الغائبين.' 
                    : 'يوجد حالياً <strong style="color:#e53e3e;">' . $absent_today . '</strong> طلاب لم يتم إدخال تسميع لهم اليوم. عند انتهائك من التسميع كلياً، اضغط على الزر لاعتماد غيابهم وإشعار ذويهم.' ?>
            </p>
        </div>
        <?php if (!$is_day_closed): ?>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <form method="POST" action="finalize-absences.php" onsubmit="return confirm('هل أنت متأكد من إنهاء يوم التسميع؟ سيتم اعتبار الـ <?= $absent_today ?> طلاب المتبقين غائبين وإرسال إشعارات لهم.');">
                    <button type="submit" style="background: #e53e3e; color: white; border: none; padding: 0.8rem 1.75rem; border-radius: 8px; font-weight: bold; font-size: 1.05rem; cursor: pointer; box-shadow: 0 4px 10px rgba(229, 62, 62, 0.3); transition: all 0.2s;">
                        🔒 إنهاء اليوم واعتماد الغياب
                    </button>
                </form>
                <button onclick="document.getElementById('broadcastModal').style.display='flex'" style="background: #3182ce; color: white; border: none; padding: 0.8rem 1.75rem; border-radius: 8px; font-weight: bold; font-size: 1.05rem; cursor: pointer; box-shadow: 0 4px 10px rgba(49, 130, 206, 0.3); transition: all 0.2s;">
                    📢 إشعار عام / درس جماعي
                </button>
            </div>
        <?php else: ?>
            <button disabled style="background: #e2e8f0; color: #a0aec0; border: none; padding: 0.8rem 1.75rem; border-radius: 8px; font-weight: bold; font-size: 1.05rem; cursor: not-allowed;">
                🔒 اليوم مغلق
            </button>
        <?php endif; ?>
    </div>

            <!-- الإحصائيات -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3><?= $total_students ?></h3>
                        <p>إجمالي الطلبة</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <h3><?= $today_followups ?></h3>
                        <p>المتابعات اليوم</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-info">
                        <h3><?= $absent_today ?></h3>
                        <p>الغياب اليوم</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-info">
                        <h3><?= $behavior_warnings + $memorization_warnings ?></h3>
                        <p>إجمالي الإنذارات</p>
                    </div>
                </div>
            </section>

            <section class="info-grid">
                <div class="info-card">
                    <h3>🏆 ترتيب أفضل 3 (الشهر المختار)</h3>
                    <div class="info-item">
                        <label>المجموع الشهري</label>
                        <span>من 100</span>
                    </div>
                    <?php if (!empty($top3)): ?>
                        <?php foreach ($top3 as $i => $s): ?>
                            <div class="info-item">
                                <label><?= ($i + 1) ?>) <?= htmlspecialchars($s['full_name']) ?></label>
                                <span><?= round((float)$s['monthly_total_100'], 1) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="info-item">
                            <label>لا توجد بيانات لهذا الشهر</label>
                            <span>0</span>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 1rem; display:flex; gap: .75rem; flex-wrap: wrap;">
                        <a class="btn btn-primary" href="ranking.php?month=<?= (int)$selectedMonth ?>&year=<?= (int)$selectedYear ?>">عرض ترتيب جميع الطلبة</a>
                        <a class="btn btn-secondary" href="export-ranking-excel.php?month=<?= (int)$selectedMonth ?>&year=<?= (int)$selectedYear ?>">تصدير الترتيب (Excel)</a>
                    </div>
                </div>

                <div class="info-card">
                    <h3>📈 معدل الحلقة (الشهر المختار)</h3>
                    <div class="info-item">
                        <label>المعدل الشهري</label>
                        <span><?= round($circle_avg_100, 1) ?> / 100</span>
                    </div>
                    <div class="info-item">
                        <label>آخر تحديث</label>
                        <span><?= date('Y-m-d') ?></span>
                    </div>
                    <div style="margin-top: 1rem; display:flex; gap: .75rem; flex-wrap: wrap;">
                        <a class="btn btn-outline" href="export-all.php?month=<?= (int)$selectedMonth ?>&year=<?= (int)$selectedYear ?>">تقرير شامل (Excel)</a>
                        <a class="btn btn-primary" href="all-students.php">عرض الطلبة</a>
                    </div>
                </div>
            </section>
            
            <!-- الروابط السريعة -->
            <section class="quick-actions">
                <h2>إجراءات سريعة</h2>
                <div class="actions-grid">
                    <a href="register-student.php" class="action-card">
                        <div class="action-icon">➕</div>
                        <h3>تسجيل طالب جديد</h3>
                        <p>إضافة طالب جديد للحلقة</p>
                    </a>
                    
                    <a href="daily-followup.php" class="action-card">
                        <div class="action-icon">📝</div>
                        <h3>متابعة يومية</h3>
                        <p>إدخال متابعة الطلبة اليوم</p>
                    </a>
                    
                    <a href="all-students.php" class="action-card">
                        <div class="action-icon">👥</div>
                        <h3>عرض الطلبة</h3>
                        <p>عرض وإدارة بيانات الطلبة</p>
                    </a>
                    
                    <a href="ranking.php" class="action-card">
                        <div class="action-icon">🏆</div>
                        <h3>الترتيب</h3>
                        <p>عرض ترتيب الطلبة</p>
                    </a>
                </div>
            </section>

</main>

<!-- نافذة إرسال إشعار عام / درس جماعي -->
<div id="broadcastModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div style="background: white; border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; animation: fadeInUp 0.3s ease;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #edf2f7; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #2b6cb0, #3182ce);">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span style="font-size: 1.5rem;">📢</span>
                <h3 style="margin: 0; color: white; font-size: 1.15rem; font-weight: 700;">إرسال إشعار عام لجميع الطلبة</h3>
            </div>
            <button onclick="document.getElementById('broadcastModal').style.display='none'" style="background: none; border: none; font-size: 1.75rem; color: rgba(255,255,255,0.8); cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <form method="POST" action="broadcast-message.php" onsubmit="return confirm('سيتم إرسال هذا الإشعار لجميع الطلبة وإغلاق اليوم (لن يُحتسب غياب). هل أنت متأكد؟');">
            <div style="padding: 1.5rem;">
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.95rem; color: #2d3748; font-weight: 700; margin-bottom: 0.5rem;">عنوان الإشعار</label>
                    <input type="text" name="broadcast_title" placeholder="مثال: درس جماعي - فقه الصلاة" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 1rem; font-family: inherit; outline: none; box-sizing: border-box; transition: border 0.2s;" onfocus="this.style.borderColor='#3182ce'" onblur="this.style.borderColor='#cbd5e0'">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.95rem; color: #2d3748; font-weight: 700; margin-bottom: 0.5rem;">نص الرسالة <span style="color: #e53e3e;">*</span></label>
                    <textarea name="broadcast_message" rows="5" required placeholder="اكتب الرسالة التي تريد إيصالها لجميع الطلبة وأولياء الأمور..." style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 1rem; font-family: inherit; outline: none; resize: vertical; box-sizing: border-box; transition: border 0.2s;" onfocus="this.style.borderColor='#3182ce'" onblur="this.style.borderColor='#cbd5e0'"></textarea>
                </div>
                <div style="background: #ebf8ff; border: 1px solid #bee3f8; border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.85rem; color: #2b6cb0; display: flex; align-items: center; gap: 0.5rem;">
                    <span>💡</span> سيتم إغلاق اليوم تلقائياً ولن يُحتسب أي غياب على الطلاب.
                </div>
            </div>
            <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #edf2f7; display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('broadcastModal').style.display='none'" style="background: #edf2f7; color: #4a5568; border: none; padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.95rem;">إلغاء</button>
                <button type="submit" style="background: #3182ce; color: white; border: none; padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 0.95rem; box-shadow: 0 2px 6px rgba(49,130,206,0.3);">📤 إرسال للجميع</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>