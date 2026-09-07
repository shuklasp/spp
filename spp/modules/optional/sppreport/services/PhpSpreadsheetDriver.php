<?php
namespace SPPMod\SPPReport\Services;

use SPPMod\SPPReport\ReportController;

class PhpSpreadsheetDriver
{
    public function export(\SPPReport $report, array $config, ReportController $controller): void
    {
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            $dataGenerator = $report->streamReport($config);
            $rowNum = 1;
            $first = true;
            
            foreach ($dataGenerator as $row) {
                if ($first) {
                    $colNum = 1;
                    foreach (array_keys($row) as $header) {
                        $sheet->setCellValueByColumnAndRow($colNum, $rowNum, $header);
                        $sheet->getStyleByColumnAndRow($colNum, $rowNum)->getFont()->setBold(true);
                        $colNum++;
                    }
                    $rowNum++;
                    $first = false;
                }
                
                $colNum = 1;
                foreach ($row as $val) {
                    // Try to cast to numeric to prevent "number stored as text" warnings
                    if (is_numeric($val) && !preg_match('/^0\d+/', $val)) {
                        $sheet->setCellValueExplicitByColumnAndRow($colNum, $rowNum, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    } else {
                        $sheet->setCellValueExplicitByColumnAndRow($colNum, $rowNum, (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    $colNum++;
                }
                $rowNum++;
            }
            
            // Auto-size columns
            foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="report_export_' . date('Ymd_His') . '.xlsx"');
            header('Cache-Control: max-age=0');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }
        
        // Fallback to legacy HTML-XLS if PhpSpreadsheet is missing
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"report_export_" . date('Ymd_His') . ".xls\"");
        echo $controller->renderExternalPartial('partials/export_excel.php', ['generator' => $report->streamReport($config)]);
        exit;
    }
}
