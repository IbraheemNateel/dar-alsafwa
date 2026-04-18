<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
try {
    $pdo->exec("ALTER TABLE students ADD COLUMN api_token VARCHAR(128) DEFAULT NULL UNIQUE");
    echo "Done";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Already exists";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
