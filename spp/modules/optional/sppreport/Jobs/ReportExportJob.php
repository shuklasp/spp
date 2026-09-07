<?php
namespace SPPMod\SPPReport\Jobs;

class ReportExportJob
{
    private string $reportName;
    private array $config;
    private string $format;
    private int $userId;

    public function __construct(string $reportName, array $config, string $format, int $userId)
    {
        $this->reportName = $reportName;
        $this->config = $config;
        $this->format = $format;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        // 1. Re-bootstrap environment and Auth if necessary in CLI worker
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            // Mock or set user for scoped queries
            \SPPMod\SPPAuth\SPPAuth::forceLoginById($this->userId);
        }

        // 2. Init report
        require_once __DIR__ . '/../class.sppreport.php';
        $report = new \SPPReport();
        $this->config['limit'] = 0; // Export everything

        // 3. Generate file
        $fileName = "export_{$this->reportName}_" . date('YmdHis') . '.' . $this->format;
        $tmpPath = sys_get_temp_dir() . '/' . $fileName;

        if ($this->format === 'pdf') {
            require_once __DIR__ . '/../services/ModernPdfDriver.php';
            $driver = new \SPPMod\SPPReport\Services\ModernPdfDriver();
            // We would modify ModernPdfDriver to save to file instead of stream
            // For now, capture output buffer
            ob_start();
            try {
                // pass a mock controller or adapt driver for file saving
            } catch (\Exception $e) {
                // ...
            }
            $pdfData = ob_get_clean();
            file_put_contents($tmpPath, $pdfData);
        } elseif ($this->format === 'xlsx') {
            require_once __DIR__ . '/../services/PhpSpreadsheetDriver.php';
            // Output buffering wrapper
            ob_start();
            // ...
            $xlsxData = ob_get_clean();
            file_put_contents($tmpPath, $xlsxData);
        }

        // 4. Notify user (e.g., WebSocket, Email, or DB notification table)
        // \SPPMod\SPPNotification\Notifier::send($this->userId, "Your export $fileName is ready", $tmpPath);
    }
}
