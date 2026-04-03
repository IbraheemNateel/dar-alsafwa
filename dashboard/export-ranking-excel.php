<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

try {
    [$selectedMonth, $selectedYear] = getSelectedMonthYear();
    [$monthStart, $monthEnd] = getMonthDateRange($selectedMonth, $selectedYear);

    // جلب ترتيب الطلبة حسب المجموع الشهري من 100 (الشهر الحالي)
    $stmt = $pdo->prepare("SELECT s.*,
                        COUNT(d.id) as total_followups,
                        AVG(d.memorization_rating) as avg_memorization,
                        AVG(d.review_rating) as avg_review,
                        AVG(d.behavior_rating) as avg_behavior,
                        COALESCE(AVG((d.memorization_rating/5*100 + d.review_rating/5*100 + d.behavior_rating/10*100)/3), 0) as monthly_total_100,
                        MAX(d.followup_date) as last_followup
                        FROM students s 
                        LEFT JOIN daily_followup d ON s.id = d.student_id 
                            AND d.followup_date BETWEEN ? AND ?
                        GROUP BY s.id 
                        HAVING total_followups > 0
                        ORDER BY monthly_total_100 DESC, avg_memorization DESC, avg_review DESC");
    $stmt->execute([$monthStart, $monthEnd]);
    $students = $stmt->fetchAll();
    
    // إنشاء ملف Excel
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);
    $spreadsheet->getDefaultStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    
    // إعداد العناوين
    $headers = [
        'الترتيب',
        'اسم الطالب',
        'رقم الهوية',
        'إجمالي المتابعات',
        'متوسط الحفظ',
        'متوسط المراجعة',
        'متوسط السلوك',
        'المجموع الشهري (من 100)',
        'آخر متابعة'
    ];
    
    $sheet->fromArray($headers, null, 'A1');
    
    // تنسيق العناوين
    $headerStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ];
    $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
    
    // إضافة البيانات
    $row = 2;
    $rank = 1;
    foreach ($students as $student) {
        $sheet->setCellValue('A' . $row, $rank++);
        $sheet->setCellValue('B' . $row, $student['full_name']);
        $sheet->setCellValue('C' . $row, $student['student_id']);
        $sheet->setCellValue('D' . $row, $student['total_followups']);
        $sheet->setCellValue('E' . $row, $student['avg_memorization'] ? round($student['avg_memorization'], 2) : 0);
        $sheet->setCellValue('F' . $row, $student['avg_review'] ? round($student['avg_review'], 2) : 0);
        $sheet->setCellValue('G' . $row, $student['avg_behavior'] ? round($student['avg_behavior'], 2) : 0);
        $sheet->setCellValue('H' . $row, round((float)$student['monthly_total_100'], 1));
        $sheet->setCellValue('I' . $row, $student['last_followup'] ? date('Y-m-d', strtotime($student['last_followup'])) : 'لا يوجد');
        $row++;
    }
    
    // تنسيق الأعمدة
    foreach (range('A', 'I') as $column) {
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
    $sheet->getStyle('A1:I' . ($row - 1))->applyFromArray($styleArray);
    
    // تمييز أفضل 3 طلاب
    if (count($students) >= 3) {
        $goldStyle = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFD700']],
            'font' => ['bold' => true]
        ];
        $silverStyle = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C0C0C0']],
            'font' => ['bold' => true]
        ];
        $bronzeStyle = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CD7F32']],
            'font' => ['bold' => true]
        ];
        
        $sheet->getStyle('A2:I2')->applyFromArray($goldStyle);  // الأول
        $sheet->getStyle('A3:I3')->applyFromArray($silverStyle); // الثاني
        $sheet->getStyle('A4:I4')->applyFromArray($bronzeStyle); // الثالث
    }
    
    // إنشاء الملف للتحميل
    $filename = 'ترتيب_الطلبة_' . $selectedYear . '-' . str_pad((string)$selectedMonth, 2, '0', STR_PAD_LEFT) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    error_log("Error exporting ranking: " . $e->getMessage());
    
    // إعادة التوجيه مع رسالة خطأ
    header('Location: ranking.php?error=فشل_في_تصدير_الترتيب');
    exit;
}
?>