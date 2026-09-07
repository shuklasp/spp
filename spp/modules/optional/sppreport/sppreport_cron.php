<?php
/**
 * SPPReport Scheduled Worker / ReportSchedulerService
 * Encapsulates report scheduling, cron matching, threshold webhooks, and email notifications.
 */

namespace SPPMod\SPPReport;

require_once __DIR__ . '/class.sppreport.php';

class ReportSchedulerService
{
    public function runScheduledReports(): void
    {
        $dir = __DIR__ . '/../../../../etc/sppreports';
        if (!is_dir($dir)) {
            echo "No reports directory found.\n";
            return;
        }

        $files = array_merge(glob($dir . '/*.yml'), glob($dir . '/*.json'));
        if (empty($files)) {
            echo "No reports configured.\n";
            return;
        }

        $reportEngine = new \SPPReport();

        foreach ($files as $file) {
            $config = $this->parseReportConfig($file);
            $cronSchedule = $config['cron_schedule'] ?? '';
            $cronEmail = $config['cron_email'] ?? '';
            $exportFormat = $config['cron_format'] ?? 'html';
            $webhookUrl = $config['webhook_url'] ?? '';
            $webhookCondition = $config['webhook_condition'] ?? '';

            if (empty($cronSchedule)) {
                continue;
            }

            if ($this->matchCron($cronSchedule)) {
                echo "Executing scheduled report: " . basename($file) . "\n";

                if (empty($config['table'])) {
                    continue;
                }

                $config['limit'] = 0; // Export everything
                $result = $reportEngine->runReport($config);

                // --- Materialized Snapshots ---
                if (!empty($config['materialized'])) {
                    if (!class_exists('\\SPPMod\\SPPReport\\Services\\SnapshotService')) {
                        require_once __DIR__ . '/services/SnapshotService.php';
                    }
                    $snapshotSvc = new \SPPMod\SPPReport\Services\SnapshotService();
                    // Pass an array iterator as a generator fallback
                    $dataStream = (function() use ($result) {
                        foreach ($result['data'] as $r) yield $r;
                    })();
                    $snapshotSvc->createSnapshot(basename($file, '.yml'), $dataStream);
                    echo "Materialized snapshot updated for " . basename($file) . "\n";
                }

                // --- Webhook Logic ---
                if (!empty($webhookUrl) && !empty($webhookCondition) && !empty($result['data'])) {
                    $ops = ['>=', '<=', '!=', '=', '>', '<'];
                    $matchedOp = null;
                    $parts = [];
                    foreach ($ops as $op) {
                        if (strpos($webhookCondition, $op) !== false) {
                            $matchedOp = $op;
                            $parts = explode($op, $webhookCondition);
                            break;
                        }
                    }
                    if ($matchedOp && count($parts) == 2) {
                        $colName = trim($parts[0]);
                        $targetVal = floatval(trim($parts[1]));
                        $actualVal = floatval($result['data'][0][$colName] ?? 0);
                        $isTriggered = false;
                        switch ($matchedOp) {
                            case '>': $isTriggered = $actualVal > $targetVal; break;
                            case '<': $isTriggered = $actualVal < $targetVal; break;
                            case '>=': $isTriggered = $actualVal >= $targetVal; break;
                            case '<=': $isTriggered = $actualVal <= $targetVal; break;
                            case '=': $isTriggered = $actualVal == $targetVal; break;
                            case '!=': $isTriggered = $actualVal != $targetVal; break;
                        }

                        if ($isTriggered) {
                            $payload = json_encode([
                                'text' => "Alert triggered for " . pathinfo($file, PATHINFO_FILENAME) . ": $colName ($actualVal) $matchedOp $targetVal",
                                'data' => array_slice($result['data'], 0, 5)
                            ]);
                            $ch = curl_init($webhookUrl);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_exec($ch);
                            curl_close($ch);
                            echo "Webhook fired to $webhookUrl\n";
                        }
                    }
                }

                if (empty($cronEmail)) {
                    continue;
                }

                // --- AI-Driven Anomaly Detection ---
                if (!empty($config['alert_condition']) && !empty($result['data'])) {
                    if (!class_exists('\\SPPMod\\SPPReport\\Services\\AiReportService')) {
                        require_once __DIR__ . '/services/AiReportService.php';
                    }
                    $aiSvc = new \SPPMod\SPPReport\Services\AiReportService();
                    $isAnomaly = $aiSvc->evaluateAnomaly($config['alert_condition'], array_slice($result['data'], 0, 100));
                    if (!$isAnomaly) {
                        echo "AI determined anomaly condition NOT met. Silencing email.\n";
                        continue;
                    } else {
                        echo "AI detected anomaly! Firing email alert.\n";
                    }
                }

                $subject = "Scheduled Report: " . pathinfo($file, PATHINFO_FILENAME);
                $boundary = md5((string) time());
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "From: SPPReport System <noreply@sppsystem.local>\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

                $body = "--$boundary\r\n";
                $body .= "Content-Type: text/html; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $body .= "<h1>" . htmlspecialchars($subject) . "</h1><p>Please find your automated report below.</p>";

                if (!empty($result['data'])) {
                    $body .= '<table border="1" cellpadding="5" style="border-collapse: collapse;"><thead><tr>';
                    foreach (array_keys($result['data'][0]) as $th) {
                        $body .= '<th style="background-color:#f4f4f4;">' . htmlspecialchars($th) . '</th>';
                    }
                    $body .= '</tr></thead><tbody>';

                    $csvData = implode(',', array_keys($result['data'][0])) . "\n";
                    foreach ($result['data'] as $row) {
                        $body .= '<tr>';
                        $csvRow = [];
                        foreach ($row as $val) {
                            $body .= '<td>' . htmlspecialchars((string)$val) . '</td>';
                            $csvRow[] = '"' . str_replace('"', '""', (string)$val) . '"';
                        }
                        $body .= '</tr>';
                        $csvData .= implode(',', $csvRow) . "\n";
                    }
                    $body .= '</tbody></table>';
                } else {
                    $body .= '<p>No data found.</p>';
                    $csvData = "No data\n";
                }
                $body .= "\r\n\r\n";

                if ($exportFormat === 'pdf' && class_exists('TCPDF')) {
                    $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                    $pdf->SetCreator('SPPReport');
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', '', 10);
                    $pdfHtml = "<h1>" . htmlspecialchars($subject) . "</h1>" . substr($body, strpos($body, '<table'));
                    $pdfHtml = substr($pdfHtml, 0, strrpos($pdfHtml, '</table>') + 8);
                    if (empty($result['data'])) {
                        $pdfHtml = "<h1>$subject</h1><p>No data</p>";
                    }
                    $pdf->writeHTML($pdfHtml, true, false, true, false, '');
                    $pdfData = $pdf->Output('', 'S');

                    $body .= "--$boundary\r\n";
                    $body .= "Content-Type: application/pdf; name=\"report.pdf\"\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"report.pdf\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $body .= chunk_split(base64_encode($pdfData)) . "\r\n\r\n";
                } else {
                    $body .= "--$boundary\r\n";
                    $body .= "Content-Type: text/csv; name=\"report.csv\"\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"report.csv\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $body .= chunk_split(base64_encode($csvData)) . "\r\n\r\n";
                }

                $body .= "--$boundary--";

                mail($cronEmail, $subject, $body, $headers);
                echo "Email sent to $cronEmail\n";
            }
        }
        echo "Cron run complete.\n";
    }

    private function parseReportConfig(string $file): array
    {
        if (str_ends_with($file, '.json')) {
            $decoded = json_decode(file_get_contents($file), true);
            return $decoded ?: [];
        }

        if (function_exists('yaml_parse_file')) {
            $parsed = @yaml_parse_file($file);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        $symfonyYaml = __DIR__ . '/../../../../lib/vendor/autoload.php';
        if (file_exists($symfonyYaml)) {
            require_once $symfonyYaml;
            if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
                try {
                    $parsed = \Symfony\Component\Yaml\Yaml::parseFile($file);
                    if (is_array($parsed)) {
                        return $parsed;
                    }
                } catch (\Exception $e) {
                    // Fallback below
                }
            }
        }

        $content = file_get_contents($file);
        if (preg_match("/json_dump:\s*'(.*)'$/m", $content, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) {
                return $json;
            }
        }

        $result = [];
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if (preg_match('/^([a-zA-Z0-9_]+):\s*(.*)$/', trim($line), $matches)) {
                $val = trim($matches[2]);
                if (str_starts_with($val, '"')) {
                    $val = trim($val, '"');
                } elseif (str_starts_with($val, "'")) {
                    $val = trim($val, "'");
                }
                $result[$matches[1]] = $val;
            }
        }
        return $result;
    }

    public function matchCron(string $cronExpr, ?int $currentTime = null): bool
    {
        if (!$currentTime) {
            $currentTime = time();
        }
        $parts = explode(' ', trim($cronExpr));
        if (count($parts) < 5) {
            return false;
        }

        $dateVals = [
            intval(date('i', $currentTime)),
            intval(date('H', $currentTime)),
            intval(date('d', $currentTime)),
            intval(date('m', $currentTime)),
            intval(date('w', $currentTime))
        ];

        foreach ($parts as $idx => $part) {
            if ($part === '*') {
                continue;
            }
            if (str_starts_with($part, '*/')) {
                $div = intval(substr($part, 2));
                if ($div > 0 && $dateVals[$idx] % $div !== 0) {
                    return false;
                }
            } elseif (intval($part) !== $dateVals[$idx]) {
                return false;
            }
        }
        return true;
    }
}

// Support direct execution via native Linux Cron CLI
if (!empty($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    (new ReportSchedulerService())->runScheduledReports();
}
