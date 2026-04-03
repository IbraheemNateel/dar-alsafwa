<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    if ($_SESSION['role'] === 'student') {
        header('Location: student/index.php');
    } else {
        header('Location: dashboard/index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? 'admin';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        require_once __DIR__ . '/config/database.php';
        try {
            $pdo = getConnection();
            
            if ($login_type === 'student') {
                // دخول الطالب: username = student_id, password = guardian_phone
                $stmt = $pdo->prepare("SELECT s.id, s.full_name, s.student_id FROM students s WHERE s.student_id = ? AND s.guardian_phone = ?");
                $stmt->execute([$username, $password]);
                $student = $stmt->fetch();
                
                if ($student) {
                    // إدراج أو تحديث في users
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, student_id) 
                                          VALUES (?, ?, ?, 'student', ?) 
                                          ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), student_id = VALUES(student_id)");
                    $stmt->execute([$student['student_id'], password_hash($password, PASSWORD_DEFAULT), $student['full_name'], $student['id']]);
                    
                    $user_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM users WHERE username = '{$student['student_id']}'")->fetch()['id'];
                    
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $student['student_id'];
                    $_SESSION['full_name'] = $student['full_name'];
                    $_SESSION['role'] = 'student';
                    $_SESSION['student_id'] = $student['id'];
                    header('Location: student/index.php');
                    exit;
                } else {
                    $error = 'رقم الهوية أو رقم ولي الأمر غير صحيح';
                }
            } else {
                // دخول الأدمن
                $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ? AND role = 'admin'");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: dashboard/index.php');
                    exit;
                } else {
                    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                }
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ، يرجى المحاولة لاحقاً';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - دار صفوة للتحفيظ</title>
    <link rel="shortcut icon" href="assets/images/logo.jpg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700&family=Amiri:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= getBaseUrl() ?>assets/css/main.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <img src="<?= getBaseUrl() ?>assets/images/logo.jpg" alt="دار صفوة" class="logo-img">
                    <h1 class="login-title">أكاديمية دار الصفوة لتعليم القرءان الكريم و السنة النبوية</h1>
                    <p class="login-subtitle">وَرَتِّلِ الْقُرْآنَ تَرْتِيلًا</p>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger" style="background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="login_type">نوع الدخول</label>
                        <select id="login_type" name="login_type" onchange="toggleLabels()">
                            <option value="admin">دخول المحفظ</option>
                            <option value="student">دخول الطالب</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="username" id="username_label">اسم المستخدم</label>
                        <input type="text" id="username" name="username" placeholder="أدخل اسم المستخدم" required 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="password" id="password_label">كلمة المرور</label>
                        <input type="password" id="password" name="password" placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="login-btn">تسجيل الدخول</button>
                </form>
                
                <script>
                function toggleLabels() {
                    const loginType = document.getElementById('login_type').value;
                    const usernameLabel = document.getElementById('username_label');
                    const passwordLabel = document.getElementById('password_label');
                    const usernameInput = document.getElementById('username');
                    const passwordInput = document.getElementById('password');
                    
                    if (loginType === 'student') {
                        usernameLabel.textContent = 'رقم الهوية';
                        usernameInput.placeholder = 'أدخل رقم الهوية';
                        passwordLabel.textContent = 'رقم ولي الأمر';
                        passwordInput.placeholder = 'أدخل رقم ولي الأمر';
                    } else {
                        usernameLabel.textContent = 'اسم المستخدم';
                        usernameInput.placeholder = 'أدخل اسم المستخدم';
                        passwordLabel.textContent = 'كلمة المرور';
                        passwordInput.placeholder = 'أدخل كلمة المرور';
                    }
                }
                </script>
            </div>
        </div>
    </div>
</body>
</html>
