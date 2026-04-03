<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

try {
    // جلب بيانات الطلبة الأساسية
    $stmt = $pdo->query("SELECT * FROM students ORDER BY full_name");
    $students = $stmt->fetchAll();
    
    // إنشاء ملف Excel
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // إعداد العناوين
    $headers = [
        'اسم الطالب',
        'رقم الهوية',
        'تاريخ الميلاد',
        'رقم هوية الأب',
        'رقم ولي الأمر',
        'تاريخ التسجيل'
    ];
    
    $sheet->fromArray($headers, null, 'A1');
    
    // تنسيق العناوين
    $headerStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
    
    // إضافة البيانات
    $row = 2;
    foreach ($students as $student) {
        $sheet->setCellValue('A' . $row, $student['full_name']);
        $sheet->setCellValue('B' . $row, $student['student_id']);
        $sheet->setCellValue('C' . $row, date('Y-m-d', strtotime($student['birth_date'])));
        $sheet->setCellValue('D' . $row, $student['father_id']);
        $sheet->setCellValue('E' . $row, $student['guardian_phone']);
        $sheet->setCellValue('F' . $row, date('Y-m-d', strtotime($student['created_at'])));
        $row++;
    }
    
    // تنسيق الأعمدة
    foreach (range('A', 'F') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // إضافة حدود
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ];
    $sheet->getStyle('A1:F' . ($row - 1))->applyFromArray($styleArray);
    
    // إنشاء الملف للتحميل
    $filename = 'قائمة_الطلبة_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    error_log("Error exporting students: " . $e->getMessage());
    
    // إعادة التوجيه مع رسالة خطأ
    header('Location: all-students.php?error=فشل_في_تصدير_قائمة_الطلبة');
    exit;
}
?>