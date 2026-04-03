<?php
require_once __DIR__ . '/config/database.php';

$pdo = getConnection();

try {
    // إضافة عمود role إلى users
    $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin', 'student') DEFAULT 'admin'");
    $pdo->exec("ALTER TABLE users ADD COLUMN student_id INT NULL");
    $pdo->exec("ALTER TABLE users ADD FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE");

    // جدول الإشعارات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            type ENUM('followup', 'absence', 'warning_behavior', 'warning_memorization') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            payload JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "تم تحديث قاعدة البيانات بنجاح!";
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage();
}
?>