<?php
namespace SPPMod\SPPReport\Services;

use SPPMod\SPPReport\ReportController;

class ModernPdfDriver
{
    public function export(\SPPReport $report, array $config, ReportController $controller): void
    {
        $result = $report->runReport($config);
        $html = $controller->renderExternalPartial('partials/print_modern.php', [
            'data' => $result['data'],
            'config' => $config,
            'org_name' => 'SPP Global Enterprise Solutions'
        ]);

        // Proper degradation logic as requested
        if (class_exists('\\Dompdf\\Dompdf')) {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $dompdf->stream('report_export_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
            exit;
        } 
        
        // Fallback to TCPDF
        if (class_exists('TCPDF')) {
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('SPPReport');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage('L');
            $pdf->SetFont('helvetica', '', 10);
            
            // Re-render using legacy TCPDF partial because TCPDF fails on modern CSS
            $legacyHtml = $controller->renderExternalPartial('partials/export_pdf.php', ['data' => $result['data']]);
            $pdf->writeHTML($legacyHtml, true, false, true, false, '');
            $pdf->Output('report_export_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        }

        throw new \Exception("Server-Side PDF rendering requires either Dompdf or TCPDF to be installed. Run `composer require dompdf/dompdf`.");
    }
}
