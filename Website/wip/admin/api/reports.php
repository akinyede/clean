<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

ensure_api_authenticated(['admin', 'manager']);

// Check if this is an export request
if (isset($_GET['format'])) {
    handle_export();
    exit;
}

header('Content-Type: application/json');

$month = $_GET['month'] ?? date('Y-m');
$monthStart = DateTimeImmutable::createFromFormat('Y-m', $month)->modify('first day of this month');
$monthEnd = $monthStart->modify('last day of this month');
$weekStart = (new DateTimeImmutable('monday this week'));
$weekEnd = $weekStart->modify('+6 days');

$conn = db();

$weeklyStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE appointment_date BETWEEN ? AND ?
    AND status IN ('pending', 'confirmed', 'completed')
");
$weekStartStr = $weekStart->format('Y-m-d');
$weekEndStr = $weekEnd->format('Y-m-d');
$weeklyStmt->bind_param('ss', $weekStartStr, $weekEndStr);
$weeklyStmt->execute();
$weeklyResult = $weeklyStmt->get_result()->fetch_assoc();
$weeklyStmt->close();

$revenueStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total END), 0) AS paid_total,
        COALESCE(SUM(total), 0) AS overall_total
    FROM invoices
    WHERE issue_date BETWEEN ? AND ?
");
$monthStartStr = $monthStart->format('Y-m-d');
$monthEndStr = $monthEnd->format('Y-m-d');
$revenueStmt->bind_param('ss', $monthStartStr, $monthEndStr);
$revenueStmt->execute();
$revenueResult = $revenueStmt->get_result()->fetch_assoc();
$revenueStmt->close();

$statusCounts = [
    'scheduled' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

$statusStmt = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM bookings
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY status
");
$statusStmt->bind_param('ss', $weekStartStr, $weekEndStr);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
while ($row = $statusResult->fetch_assoc()) {
    $status = strtolower($row['status'] ?? '');
    if (array_key_exists($status, $statusCounts)) {
        $statusCounts[$status] = (int) $row['total'];
    }
}
$statusStmt->close();

$clientCount = 0;
$clientResult = $conn->query("SELECT COUNT(*) AS total FROM customers");
if ($clientResult && $data = $clientResult->fetch_assoc()) {
    $clientCount = (int) $data['total'];
    $clientResult->free();
}

$staffCount = 0;
$staffResult = $conn->query("SELECT COUNT(*) AS total FROM staff WHERE is_active = 1");
if ($staffResult && $data = $staffResult->fetch_assoc()) {
    $staffCount = (int) $data['total'];
    $staffResult->free();
}

$upcomingStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND status_label IN ('scheduled', 'in_progress')
");
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result()->fetch_assoc();
$upcomingStmt->close();

json_response([
    'success' => true,
    'weekly_bookings' => (int) ($weeklyResult['total'] ?? 0),
    'monthly_revenue' => format_currency((float) ($revenueResult['paid_total'] ?? 0)),
    'overall_revenue' => format_currency((float) ($revenueResult['overall_total'] ?? 0)),
    'total_clients' => $clientCount,
    'active_staff' => $staffCount,
    'upcoming_jobs' => (int) ($upcomingResult['total'] ?? 0),
    'status_counts' => $statusCounts,
    'scheduled_count' => $statusCounts['scheduled'],
    'in_progress_count' => $statusCounts['in_progress'],
    'completed_count' => $statusCounts['completed'],
    'cancelled_count' => $statusCounts['cancelled'],
    'periods' => [
        'week_start' => $weekStartStr,
        'week_end' => $weekEndStr,
        'month_start' => $monthStartStr,
        'month_end' => $monthEndStr,
    ],
]);

function handle_export(): void
{
    $format = $_GET['format'] ?? 'csv';
    $reportType = $_GET['report_type'] ?? 'overview';
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');
    
    $conn = db();
    
    // Get bookings data for export
    $stmt = $conn->prepare("
        SELECT
            b.booking_id,
            CONCAT(b.first_name, ' ', b.last_name) AS customer_name,
            b.email,
            b.phone,
            b.service_type,
            b.appointment_date,
            b.appointment_time,
            b.status_label,
            b.estimated_price,
            b.final_price,
            b.address,
            b.city,
            b.state,
            b.zip
        FROM bookings b
        WHERE b.appointment_date BETWEEN ? AND ?
        ORDER BY b.appointment_date DESC
    ");
    $stmt->bind_param('ss', $dateFrom, $dateTo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    
    switch ($format) {
        case 'csv':
            export_csv($data, $reportType);
            break;
        case 'xlsx':
            export_excel($data, $reportType);
            break;
        case 'pdf':
            export_pdf($data, $reportType);
            break;
        default:
            header('Content-Type: application/json');
            json_response(['success' => false, 'message' => 'Invalid format'], 400);
    }
}

function export_csv(array $data, string $reportType): void
{
    $filename = 'report_' . $reportType . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function export_excel(array $data, string $reportType): void
{
    // Check if PhpSpreadsheet is available
    if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback to CSV with xlsx extension
        $filename = 'report_' . $reportType . '_' . date('Y-m-d') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        return;
    }
    
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set title
    $sheet->setTitle(ucfirst($reportType));
    
    // Add headers
    if (!empty($data)) {
        $headers = array_keys($data[0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', ucfirst(str_replace('_', ' ', $header)));
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $col++;
        }
        
        // Add data
        $row = 2;
        foreach ($data as $dataRow) {
            $col = 'A';
            foreach ($dataRow as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', $col) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }
    
    // Output file
    $filename = 'report_' . $reportType . '_' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function export_pdf(array $data, string $reportType): void
{
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $filename = 'report_' . $reportType . '_' . date('Y-m-d') . '.pdf';
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Wasatch Cleaners');
    $pdf->SetAuthor('Wasatch Cleaners');
    $pdf->SetTitle('Report: ' . ucfirst($reportType));
    $pdf->SetSubject('Business Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Wasatch Cleaners - ' . ucfirst($reportType) . ' Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Build HTML table
    $html = '<table border="1" cellpadding="4" cellspacing="0" style="width: 100%; font-size: 9px;">
        <thead>
            <tr style="background-color: #14b8a6; color: white; font-weight: bold;">
                <th>Booking ID</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Date</th>
                <th>Status</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>';
    
    if (!empty($data)) {
        foreach ($data as $row) {
            $price = !empty($row['final_price']) ? '$' . number_format((float)$row['final_price'], 2) : '$' . number_format((float)$row['estimated_price'], 2);
            $html .= '<tr>
                <td>' . htmlspecialchars($row['booking_id']) . '</td>
                <td>' . htmlspecialchars($row['customer_name']) . '</td>
                <td>' . htmlspecialchars($row['service_type']) . '</td>
                <td>' . htmlspecialchars($row['appointment_date']) . '</td>
                <td>' . htmlspecialchars($row['status_label']) . '</td>
                <td>' . $price . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" style="text-align: center;">No data available</td></tr>';
    }
    
    $html .= '</tbody></table>';
    
    // Print table
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Add summary
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, 'Summary', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Total Records: ' . count($data), 0, 1, 'L');
    
    // Calculate total revenue
    $totalRevenue = 0;
    foreach ($data as $row) {
        $price = !empty($row['final_price']) ? (float)$row['final_price'] : (float)$row['estimated_price'];
        $totalRevenue += $price;
    }
    $pdf->Cell(0, 6, 'Total Revenue: $' . number_format($totalRevenue, 2), 0, 1, 'L');
    
    // Output PDF
    $pdf->Output($filename, 'D');
    exit;
}