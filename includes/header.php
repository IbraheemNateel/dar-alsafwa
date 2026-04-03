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
            </div>
