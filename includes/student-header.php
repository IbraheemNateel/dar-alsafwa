<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../dashboard/index.php');
    exit;
}

$page_title = $page_title ?? 'صفحة الطالب';

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
    <div class="student-layout">
        <header class="student-header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-bottom: 2px solid rgba(255,255,255,0.05);">
            <div class="student-header-content" style="padding: 0.75rem 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 1rem; flex-shrink: 0;">
                    <img src="<?= getBaseUrl() ?>assets/images/logo.jpg" alt="دار صفوة" class="student-logo" style="width: 50px; height: 50px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 2px solid rgba(255,255,255,0.2);">
                    <div style="display: flex; flex-direction: column;">
                        <h1 style="font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.5px; white-space: nowrap; color: #ffffff; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">أكاديمية دار الصفوة</h1>
                        <small style="color: #cbd5e1; font-weight: 600; font-size: 0.85rem; opacity: 0.9;">لتعليم القرآن الكريم والسنة النبوية</small>
                    </div>
                </div>
                <div class="student-info" style="background: rgba(255,255,255,0.05); padding: 0.5rem 0.5rem 0.5rem 1.25rem; border-radius: 50px; display: flex; gap: 1.25rem; align-items: center; flex-shrink: 0; white-space: nowrap; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                    <span style="font-size: 1rem; font-weight: 600; color: #f8fafc;">مرحباً بك، <strong style="color: #60a5fa; text-shadow: 0 1px 2px rgba(0,0,0,0.2);"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?></strong> 👋</span>
                    <a href="<?= getBaseUrl() ?>logout.php" class="logout-link" style="background: linear-gradient(to bottom, #ef4444, #dc2626); color: white; padding: 0.5rem 1.25rem; border-radius: 25px; font-size: 0.9rem; font-weight: 800; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.4); text-decoration: none; transition: all 0.2s ease; display: flex; align-items: center; gap: 0.5rem;">خروج <span style="font-size: 1.1rem;">🚪</span></a>
                </div>
            </div>
        </header>