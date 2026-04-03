-- دار صفوة - قاعدة بيانات حلقة تحفيظ القرآن
-- Dar Safwa - Quran Memorization Circle Database Schema

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS dar_safwa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dar_safwa_db;

-- جدول المستخدمين (المحفظ والطلاب)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'student') DEFAULT 'admin',
    student_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الطلبة
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    student_id VARCHAR(20) NOT NULL,
    birth_date DATE NOT NULL,
    father_id VARCHAR(20) NOT NULL,
    guardian_phone VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول المتابعة اليومية
CREATE TABLE IF NOT EXISTS daily_followup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    followup_date DATE NOT NULL,
    day_name VARCHAR(20) NOT NULL,
    followup_time TIME NOT NULL,
    memorization_from VARCHAR(50) DEFAULT NULL,
    memorization_to VARCHAR(50) DEFAULT NULL,
    memorization_rating TINYINT DEFAULT 0,
    review_from VARCHAR(50) DEFAULT NULL,
    review_to VARCHAR(50) DEFAULT NULL,
    review_rating TINYINT DEFAULT 0,
    behavior_rating TINYINT DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_date (student_id, followup_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الإشعارات المرسلة (لتجنب التكرار)
CREATE TABLE IF NOT EXISTS sent_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    notification_type ENUM('followup', 'absence') NOT NULL,
    notification_date DATE NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_notification (student_id, notification_type, notification_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول إشعارات الطلاب
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج مستخدم افتراضي (اسم المستخدم: admin، كلمة المرور: admin123)
-- كلمة المرور الافتراضية: admin123
INSERT INTO users (username, password, full_name) VALUES 
('admin', '$2y$10$ogNrdMJtPRkDykqMmDl/NefrMQya.V6o88ehX4jtxFs9tw1fAKKHe', 'المحفظ');
