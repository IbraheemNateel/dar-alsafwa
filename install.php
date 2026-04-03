<?php
/**
 * دار صفوة - ملف التثبيت
 * Dar Safwa - Installation Script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت دار صفوة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .step {
            background: #f8f9fa;
            border-right: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .step h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #ffeaa7;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 20px auto;
            text-align: center;
        }
        .btn:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🕌 تثبيت دار صفوة لنظام إدارة الحلقة</h1>
        
        <?php
        
        $installed = false;
        $errors = [];
        
        try {
            // التحقق من الاتصال بقاعدة البيانات
            $pdo = new PDO(
                "mysql:host=localhost;charset=utf8mb4",
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            echo '<div class="step">';
            echo '<h3>✅ الاتصال بخادم MySQL</h3>';
            echo '<p class="success">تم الاتصال بقاعدة البيانات بنجاح</p>';
            echo '</div>';
            
            // قراءة وتنفيذ ملف المخطط
            $schema = file_get_contents(__DIR__ . '/database/schema.sql');
            
            if ($schema) {
                // تنفيذ الأوامر واحدة تلو الأخرى
                $statements = array_filter(array_map('trim', explode(';', $schema)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                echo '<div class="step">';
                echo '<h3>✅ إنشاء قاعدة البيانات والجداول</h3>';
                echo '<p class="success">تم إنشاء قاعدة البيانات dar_safwa_db وجميع الجداول بنجاح</p>';
                echo '</div>';
                
                // التحقق من إنشاء المستخدم الافتراضي
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM dar_safwa_db.users WHERE username = 'admin'");
                $stmt->execute();
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    echo '<div class="step">';
                    echo '<h3>✅ إنشاء المستخدم الافتراضي</h3>';
                    echo '<p class="success">تم إنشاء المستخدم الافتراضي بنجاح</p>';
                    echo '<p><strong>اسم المستخدم:</strong> admin</p>';
                    echo '<p><strong>كلمة المرور:</strong> admin123</p>';
                    echo '</div>';
                    
                    $installed = true;
                } else {
                    $errors[] = 'فشل في إنشاء المستخدم الافتراضي';
                }
                
            } else {
                $errors[] = 'لم يتم العثور على ملف المخطط database/schema.sql';
            }
            
        } catch (Exception $e) {
            $errors[] = 'خطأ في التثبيت: ' . $e->getMessage();
        }
        
        if ($installed) {
            echo '<div class="warning">';
            echo '<h3>⚠️ مهم جداً</h3>';
            echo '<p>يرجى حذف ملف install.php فوراً لأسباب أمنية!</p>';
            echo '<p>يمكنك الدخول إلى النظام الآن من:</p>';
            echo '<p><a href="index.php" class="btn">دخول إلى النظام</a></p>';
            echo '</div>';
        } else {
            echo '<div class="step">';
            echo '<h3>❌ حدثت أخطاء</h3>';
            foreach ($errors as $error) {
                echo '<p class="error">' . htmlspecialchars($error) . '</p>';
            }
            echo '</div>';
            
            echo '<div style="text-align: center; margin-top: 30px;">';
            echo '<a href="install.php" class="btn">إعادة المحاولة</a>';
            echo '</div>';
        }
        
        ?>
        
        <div style="text-align: center; margin-top: 30px; color: #666; font-size: 14px;">
            <p>دار صفوة - نظام إدارة حلقة تحفيظ القرآن</p>
            <p>Powered by PHP & MySQL</p>
        </div>
    </div>
</body>
</html>
