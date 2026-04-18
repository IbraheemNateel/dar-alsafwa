<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireLogin();

$page_title = $page_title ?? 'لوحة التحكم';

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - دار صفوة</title>
    <link rel="manifest" href="<?= getBaseUrl() ?>manifest.json">
    <link rel="shortcut icon" href="<?= getBaseUrl() ?>assets/images/logo.jpg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700&family=Amiri:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= getBaseUrl() ?>assets/css/main.css">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="<?= getBaseUrl() ?>assets/images/logo.jpg" alt="دار صفوة" class="sidebar-logo">
                <h2>أكاديمية دار الصفوة</h2>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li><a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/index.php">🏠 الرئيسية</a></li>
                    <li><a class="<?= $currentPage === 'register-student.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/register-student.php">👤 تسجيل طالب جديد</a></li>
                    <li><a class="<?= $currentPage === 'all-students.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/all-students.php">📋 جميع الطلبة</a></li>
                    <li><a class="<?= $currentPage === 'student-report.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/student-report.php">🧾 تقرير الطالب</a></li>
                    <li><a class="<?= $currentPage === 'daily-followup.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/daily-followup.php">📝 المتابعة اليومية</a></li>
                    <li><a class="<?= $currentPage === 'ranking.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/ranking.php">🏆 الترتيب</a></li>
                    <li><a class="<?= $currentPage === 'warnings-behavior.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/warnings-behavior.php">⚠️ انذارات السلوك</a></li>
                    <li><a class="<?= $currentPage === 'warnings-memorization.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/warnings-memorization.php">📚 انذارات الحفظ</a></li>
                    <li><a class="<?= $currentPage === 'absence.php' ? 'active' : '' ?>" href="<?= getBaseUrl() ?>dashboard/absence.php">📅 الغياب</a></li>
                    <li><a href="<?= getBaseUrl() ?>logout.php">🚪 تسجيل الخروج</a></li>
                </ul>
            </nav>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay" hidden></div>

        <div class="page-container">
            <div class="topbar">
                <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="فتح القائمة">☰</button>
                <div class="topbar-title">أكاديمية دار الصفوة</div>
                <div id="syncIndicator" title="يوجد بيانات محفوظة محلياً بانتظار الاتصال بالنت" onclick="attemptSync()" style="display: none; background: #e67e22; color: white; padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 700; margin-right: auto; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                    أوفلاين 📡
                </div>
            </div>

            <script>
                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', () => {
                        navigator.serviceWorker.register('<?= getBaseUrl() ?>sw.js')
                            .then(reg => console.log('SW registered:', reg))
                            .catch(err => console.log('SW registration failed:', err));
                    });
                }

                // تحديث حالة الاتصال
                function updateOnlineStatus() {
                    let pendingStr = localStorage.getItem('pending_followups');
                    let pending = pendingStr ? JSON.parse(pendingStr) : [];
                    let ind = document.getElementById('syncIndicator');
                    
                    if (!navigator.onLine) {
                        ind.style.display = 'block';
                        ind.style.background = '#e67e22';
                        ind.innerHTML = pending.length > 0 ? `أوفلاين - ${pending.length} للرفع ⏳` : 'وضع الأوفلاين 📡';
                    } else {
                        if (pending.length > 0) {
                            ind.style.display = 'block';
                            ind.style.background = '#3498db';
                            ind.innerHTML = 'جاري المزامنة... 🔄';
                            attemptSync();
                        } else {
                            ind.style.display = 'none';
                        }
                    }
                }

                async function attemptSync() {
                    if (!navigator.onLine) return;
                    let pendingStr = localStorage.getItem('pending_followups');
                    let pending = pendingStr ? JSON.parse(pendingStr) : [];
                    if (pending.length === 0) return;

                    try {
                        let response = await fetch('<?= getBaseUrl() ?>api/v1/sync_offline.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(pending)
                        });
                        let result = await response.json();
                        if (result.success) {
                            localStorage.removeItem('pending_followups');
                            updateOnlineStatus();
                            alert('تم مزامنة ' + result.synced_count + ' متابعة بنجاح!');
                            if(window.location.pathname.includes('daily-followup.php')) {
                                window.location.reload();
                            }
                        }
                    } catch(e) {
                        console.error('Sync failed:', e);
                    }
                }

                window.addEventListener('online', updateOnlineStatus);
                window.addEventListener('offline', updateOnlineStatus);
                setInterval(updateOnlineStatus, 5000); // تحديث دوري
            </script>
