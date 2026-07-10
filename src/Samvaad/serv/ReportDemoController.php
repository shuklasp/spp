<?php
namespace App\Samvaad\Serv;

use SPPMod\SPPView\ViewController;

/**
 * ============================================================================
 * ReportDemoController — Demonstrates SPPReport V5 Smart Content Negotiation
 * ============================================================================
 *
 * ARCHITECTURAL CONSTRAINTS OBSERVED:
 * 1. Zero Inline HTML Literals: Absolutely no raw HTML string literals exist in this controller.
 * 2. External Partials & Views: All rendering defers to external partials or Blade templates.
 * 3. Smart Content Negotiation: Automatically inspects `HX-Request`, `Turbo-Frame`, and `Accept`
 *    headers to serve appropriate partials, Turbo Streams, or full page renders.
 * ============================================================================
 */
class ReportDemoController extends ViewController
{
    public function showMonthlySales()
    {
        $startDate = $_GET['start_date'] ?? '2026-06-01';
        $endDate = $_GET['end_date'] ?? '2026-06-30';

        // Simulate data retrieval (normally fetched via ReportManager / SPPDB)
        $sampleData = [
            (object)['department' => 'Enterprise Hardware', 'total_sales' => 124500.00, 'transactions' => 142],
            (object)['department' => 'Cloud Services & SaaS', 'total_sales' => 348900.50, 'transactions' => 840],
            (object)['department' => 'Professional Consulting', 'total_sales' => 89400.00, 'transactions' => 64],
        ];

        $contextData = [
            'app_name' => 'Samvaad',
            'base_url' => \SPP\App::getBaseUrl('Samvaad'),
            'report' => [
                'id' => 'monthly_sales',
                'name' => 'Monthly Sales & Performance Summary'
            ],
            'params' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'data' => $sampleData
        ];

        // ── Smart Content Negotiation ──
        $headers = getallheaders();
        $hxRequest = isset($headers['HX-Request']) || isset($_SERVER['HTTP_HX_REQUEST']);
        $turboFrame = isset($headers['Turbo-Frame']) || isset($_SERVER['HTTP_TURBO_FRAME']);
        $accept = $headers['Accept'] ?? ($_SERVER['HTTP_ACCEPT'] ?? '');

        if ($hxRequest || $turboFrame) {
            // Serve standalone partial for HTMX / Turbo Frame updates
            return $this->renderPartial('reports/monthly_sales_template', $contextData);
        } elseif (strpos($accept, 'text/vnd.turbo-stream.html') !== false) {
            // Serve real-time Turbo Stream update
            return $this->stream('streams/report_update.php', $contextData);
        }

        // Serve full standalone page render
        return $this->render('reports.monthly_sales_template', $contextData);
    }
}