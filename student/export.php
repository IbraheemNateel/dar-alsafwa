<?php
ob_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

if ($_SESSION['role'] !== 'student') {
    header('Location: ../dashboard/index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

$student_id = $_SESSION['student_id'];

[$selectedMonth, $selectedYear] = getSelectedMonthYear();
[$monthStart, $monthEnd] = getMonthDateRange($selectedMonth, $selectedYear);

try {
    // جلب بيانات الطالب
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    // جلب متابعة الطالب للشهر المختار فقط
    $stmt = $pdo->prepare("SELECT * FROM daily_followup 
                          WHERE student_id = ? AND followup_date BETWEEN ? AND ?
                          ORDER BY followup_date DESC");
    $stmt->execute([$student_id, $monthStart, $monthEnd]);
    $followups = $stmt->fetchAll();
    
    // جلب أيام الدروس الجماعية (البث العام) خلال الفترة
    $bcStmt = $pdo->prepare("SELECT title, message, DATE(created_at) as broadcast_date 
                             FROM student_notifications 
                             WHERE student_id = ? AND type = 'broadcast' 
                             AND DATE(created_at) BETWEEN ? AND ?
                             ORDER BY created_at DESC");
    $bcStmt->execute([$student_id, $monthStart, $monthEnd]);
    $broadcasts = $bcStmt->fetchAll();
    
    // إنشاء ملف Excel
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setRightToLeft(true);
    $spreadsheet->getDefaultStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    
    // معلومات الطالب
    $sheet->setCellValue('A1', 'اسم الطالب:');
    $sheet->setCellValue('B1', $student['full_name']);
    $sheet->setCellValue('A2', 'رقم الهوية:');
    $sheet->setCellValue('B2', $student['student_id']);
    $sheet->setCellValue('A3', 'تاريخ الميلاد:');
    $sheet->setCellValue('B3', date('Y-m-d', strtotime($student['birth_date'])));
    $sheet->setCellValue('A4', 'رقم ولي الأمر:');
    $sheet->setCellValue('B4', $student['guardian_phone']);
    $sheet->setCellValue('A5', 'عن شهر:');
    $sheet->setCellValue('B5', $selectedMonth . '/' . $selectedYear);
    
    // تنسيق معلومات الطالب
    $infoStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']]
    ];
    $sheet->getStyle('A1:A5')->applyFromArray($infoStyle);
    
    // عناوين المتابعة
    $headers = [
        'التاريخ',
        'اليوم',
        'وقت المتابعة',
        'الحفظ من',
        'الحفظ إلى',
        'تقييم الحفظ',
        'المراجعة من',
        'المراجعة إلى',
        'تقييم المراجعة',
        'تقييم السلوك',
        'ملاحظات'
    ];
    
    $sheet->fromArray($headers, null, 'A7');
    
    // تنسيق العناوين
    $headerStyle = [
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
        'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ];
    $sheet->getStyle('A7:K7')->applyFromArray($headerStyle);
    
    // إضافة بيانات المتابعة
    $row = 8;
    foreach ($followups as $followup) {
        $sheet->setCellValue('A' . $row, date('Y-m-d', strtotime($followup['followup_date'])));
        $sheet->setCellValue('B' . $row, $followup['day_name']);
        $sheet->setCellValue('C' . $row, date('H:i', strtotime($followup['followup_time'])));
        $sheet->setCellValue('D' . $row, $followup['memorization_from'] ?? '');
        $sheet->setCellValue('E' . $row, $followup['memorization_to'] ?? '');
        $sheet->setCellValue('F' . $row, $followup['memorization_rating']);
        $sheet->setCellValue('G' . $row, $followup['review_from'] ?? '');
        $sheet->setCellValue('H' . $row, $followup['review_to'] ?? '');
        $sheet->setCellValue('I' . $row, $followup['review_rating']);
        $sheet->setCellValue('J' . $row, $followup['behavior_rating']);
        $sheet->setCellValue('K' . $row, $followup['notes'] ?? '');
        $row++;
    }
    
    // إضافة أيام الدروس الجماعية
    if (!empty($broadcasts)) {
        $broadcastStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2B6CB0']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        foreach ($broadcasts as $bc) {
            $sheet->setCellValue('A' . $row, $bc['broadcast_date']);
            $sheet->setCellValue('B' . $row, 'درس جماعي');
            $sheet->mergeCells('C' . $row . ':K' . $row);
            $sheet->setCellValue('C' . $row, '📢 ' . ($bc['title'] ?: 'درس جماعي') . ' - ' . mb_substr($bc['message'], 0, 80));
            $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray($broadcastStyle);
            $row++;
        }
    }
    
    // تنسيق الأعمدة
    foreach (range('A', 'K') as $column) {
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
    $sheet->getStyle('A1:B5')->applyFromArray($styleArray);
    $sheet->getStyle('A7:K' . ($row - 1))->applyFromArray($styleArray);
    
    // إحصائيات موجزة
    if (!empty($followups)) {
        $total_followups = count($followups);
        $avg_memorization = array_sum(array_column($followups, 'memorization_rating')) / $total_followups;
        $avg_review = array_sum(array_column($followups, 'review_rating')) / $total_followups;
        $avg_behavior = array_sum(array_column($followups, 'behavior_rating')) / $total_followups;

        $sum = 0;
        foreach ($followups as $f) {
            $sum += (($f['memorization_rating'] / 5) * 100 + ($f['review_rating'] / 5) * 100 + ($f['behavior_rating'] / 10) * 100) / 3;
        }
        $monthly_total_100 = $total_followups ? ($sum / $total_followups) : 0;
        
        $sheet->setCellValue('A' . ($row + 2), 'إجمالي المتابعات بالشهر:');
        $sheet->setCellValue('B' . ($row + 2), $total_followups);
        $sheet->setCellValue('A' . ($row + 3), 'متوسط تقييم الحفظ:');
        $sheet->setCellValue('B' . ($row + 3), round($avg_memorization, 2));
        $sheet->setCellValue('A' . ($row + 4), 'متوسط تقييم المراجعة:');
        $sheet->setCellValue('B' . ($row + 4), round($avg_review, 2));
        $sheet->setCellValue('A' . ($row + 5), 'متوسط تقييم السلوك:');
        $sheet->setCellValue('B' . ($row + 5), round($avg_behavior, 2));

        $sheet->setCellValue('A' . ($row + 6), 'التقييم للشهر (من 100):');
        $sheet->setCellValue('B' . ($row + 6), round($monthly_total_100, 1));
        
        $sheet->getStyle('A' . ($row + 2) . ':B' . ($row + 6))->applyFromArray($infoStyle);
        $sheet->getStyle('A' . ($row + 2) . ':B' . ($row + 6))->applyFromArray($styleArray);
    }
    
    if (ob_get_length()) ob_end_clean();
    $filename = 'تقرير_الطالب_' . str_replace(' ', '_', $student['full_name']) . '_' . $selectedYear . '-' . $selectedMonth . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    echo "Error";
    exit;
}
?>