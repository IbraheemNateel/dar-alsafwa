<?php
/**
 * Cron job لتوليد الإشعارات اليومية بعد الساعة 9 مساء
 * يجب تشغيله يومياً الساعة 9:30 م
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notification-helper.php';

try {
    $pdo = getConnection();
    processDailyNotifications($pdo);
    echo "Notifications generated successfully at " . date('Y-m-d H:i:s') . "\n";
} catch (Exception $e) {
    echo "Error generating notifications: " . $e->getMessage() . "\n";
}
?>
