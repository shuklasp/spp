<?php
/**
 * SPP Report API Controller & Modular Drivers
 * Handles frontend requests for schema introspection, querying, and exporting data.
 * Features smart content negotiation (HTMX / Turbo Streams) and zero inline HTML literals.
 */

namespace SPPMod\SPPReport;

require_once __DIR__ . '/class.sppreport.php';

if (!class_exists('\\SPP\\Core\\ResourceController')) {
    require_once __DIR__ . '/../../../../spp/core/class.resourcecontroller.php';
}

class CsvExportDriver
{
    public function export(\SPPReport $report, array $config): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_export_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        $first = true;

        foreach ($report->streamReport($config) as $row) {
            if ($first) {
                fputcsv($output, array_keys($row));
                $first = false;
            }
            $sanitizedRow = array_map(function ($val) {
                return preg_match('/^[=\+\-@]/', (string) $val) ? "'" . $val : $val;
            }, $row);
            fputcsv($output, $sanitizedRow);
        }
        fclose($output);
        exit;
    }
}

class ExcelExportDriver
{
    public function export(\SPPReport $report, array $config, ReportController $controller): void
    {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"report_export_" . date('Ymd_His') . ".xls\"");

        echo $controller->renderExternalPartial('partials/export_excel.php', ['generator' => $report->streamReport($config)]);
        exit;
    }
}

class PdfExportDriver
{
    public function export(\SPPReport $report, array $config, ReportController $controller): void
    {
        $result = $report->runReport($config);

        if (class_exists('TCPDF')) {
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('SPPReport');
            $pdf->SetAuthor('System');
            $pdf->SetTitle('Report Export');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 10);

            $html = $controller->renderExternalPartial('partials/export_pdf.php', ['data' => $result['data']]);
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output('report_export_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } else {
            throw new \Exception("Server-Side PDF rendering requires TCPDF to be installed via composer (`composer require tecnickcom/tcpdf`). For zero-dependency PDF generation, please use the Client-Side 'Print PDF' option.");
        }
    }
}

class ReportController extends \SPP\Core\ResourceController
{
    public function renderExternalPartial(string $view, array $data = []): string
    {
        return $this->renderPartial($view, $data);
    }

    public function handleRequest(): void
    {
        if (class_exists('\\SPPMod\\SPPView\\ViewLocator')) {
            \SPPMod\SPPView\ViewLocator::addPath(__DIR__ . '/views');
            \SPPMod\SPPView\ViewLocator::addPath(__DIR__);
        }

        $isHtmx = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
        $isTurboStream = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/vnd.turbo-stream.html') !== false;

        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            $user = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $action = $_POST['report_action'] ?? $_GET['report_action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
            $payload = file_get_contents('php://input');
            if (empty($payload) && isset($_POST['payload'])) {
                $payload = $_POST['payload'];
            }
        } else {
            $action = $_GET['report_action'] ?? $_GET['action'] ?? '';
            $payload = $_GET['payload'] ?? '{}';
        }

        $isSppAdmin = !empty($_SESSION['spp_admin_user']) || !empty($_SESSION['spp_admin_fallback']) || (class_exists('\\SPP\\SPPSession') && \SPP\SPPSession::sessionVarExists('__sppauth_user__'));

        $user = class_exists('\\SPPMod\\SPPAuth\\SPPAuth') ? \SPPMod\SPPAuth\SPPAuth::user() : null;
        $roleNames = [];
        if ($user && method_exists($user, 'getRoles')) {
            $roleIds = $user->getRoles();
            if (!empty($roleIds)) {
                $db = new \SPPMod\SPPDB\SPPDB();
                if ($db->tableExists('roles')) {
                    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
                    $roles = $db->execute_query("SELECT name FROM " . \SPPMod\SPPDB\SPPDB::sppTable('roles') . " WHERE id IN ($placeholders)", $roleIds);
                    $roleNames = array_column($roles, 'name');
                }
            }
        }
        $isAdmin = $isSppAdmin || in_array('admin', $roleNames);

        $adminActions = ['save', 'list_versions', 'restore_version', 'save_template', 'schema', 'ai_build', 'ai_analyze'];
        if (in_array($action, $adminActions) && !$isAdmin) {
            http_response_code(403);
            if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'This action requires Admin privileges.']);
            exit;
        }

        $payloadObj = json_decode($payload, true) ?: [];
        $reportName = $_GET['report_name'] ?? $_POST['report_name'] ?? $payloadObj['report_name'] ?? '';
        $config = $payloadObj;

        $requiresSecureLoad = in_array($action, ['preview', 'export_csv', 'export_xls', 'export_pdf', 'export_json', 'export_xml', 'render_template']);
        
        if ($requiresSecureLoad) {
            if (!$reportName) {
                throw new \Exception("A valid 'report_name' is required to load the configuration. Client-dictated payloads are no longer permitted for security.");
            }
            if (!class_exists('\\SPPMod\\SPPReport\\Repositories\\ReportRepository')) {
                require_once __DIR__ . '/Repositories/ReportRepository.php';
            }
            $repo = new \SPPMod\SPPReport\Repositories\ReportRepository();
            $config = $repo->load($reportName);
            
            // Safe filter injection
            if (!empty($payloadObj['filters']['conditions'])) {
                if (!isset($config['filters']['conditions'])) {
                    $config['filters'] = ['logic' => 'AND', 'conditions' => []];
                }
                foreach ($payloadObj['filters']['conditions'] as $cond) {
                    $config['filters']['conditions'][] = $cond;
                }
            }
        }

        $externalConfig = null;
        $hasExternalDsn = (!empty($config['external_dsn']) || !empty($_GET['external_dsn']));
        if ($hasExternalDsn) {
            if (!$isAdmin) {
                http_response_code(403);
                if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'External DSN connections require Admin privileges to prevent SSRF.']);
                exit;
            }
            $extDsn = $config['external_dsn'] ?? $_GET['external_dsn'];
            $extUser = $config['external_user'] ?? $_GET['external_user'] ?? null;
            $extPass = $config['external_pass'] ?? $_GET['external_pass'] ?? null;
            $externalConfig = [
                'dsn' => $extDsn,
                'user' => $extUser,
                'pass' => $extPass
            ];
        }

        $report = new \SPPReport($externalConfig);

        $enforceRBAC = function ($cfg) use ($isAdmin, $roleNames) {
            $allowedRoles = $cfg['roles_allowed'] ?? '';
            if (!empty($allowedRoles) && !$isAdmin) {
                $allowed = array_map('trim', explode(',', strtolower($allowedRoles)));
                $myRoles = array_map('strtolower', $roleNames);
                if (empty(array_intersect($allowed, $myRoles))) {
                    throw new \Exception("You do not have permission to view this report.");
                }
            }
        };

        try {
            switch ($action) {
                case 'dashboard':
                    if ($isHtmx) {
                        echo $this->renderExternalPartial('partials/report_dashboard.html', []);
                        break;
                    }
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Dashboard partial ready.']);
                    break;

                case 'schema':
                    if ($isHtmx) {
                        $table = $_GET['table'] ?? $_POST['table'] ?? '';
                        echo $this->renderExternalPartial('partials/report_configurator.php', [
                            'driver' => $report->getDriver(),
                            'schema' => $report->getSchema(),
                            'table' => $table
                        ]);
                        break;
                    }
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'success',
                        'driver' => $report->getDriver(),
                        'schema' => $report->getSchema()
                    ]);
                    break;

                case 'preview':
                    $enforceRBAC($config);
                    
                    $cost = $report->estimateCost($config);
                    if (isset($cost['severity']) && in_array($cost['severity'], ['high', 'critical'])) {
                        if ($isHtmx || $isTurboStream) {
                            echo "<div class='spp-alert spp-alert-warning p-4 bg-yellow-50 border-l-4 border-yellow-400'>
                                    <h4 class='text-yellow-800 font-bold'>Query Too Expensive</h4>
                                    <p class='text-yellow-700 mt-2'>This query is estimated to scan approximately " . number_format($cost['total_rows_scanned']) . " rows" . ($cost['missing_index'] ? " without an optimal index" : "") . ". Synchronous execution is blocked to prevent database instability.</p>
                                    <button class='mt-3 bg-yellow-600 text-white px-4 py-2 rounded' hx-post='?action=export_async' hx-vals='{\"report_name\":\"" . htmlspecialchars($reportName) . "\"}'>Schedule Background Export (DagJobOrchestrator)</button>
                                  </div>";
                        } else {
                            http_response_code(429);
                            echo json_encode(['status' => 'error', 'message' => 'Query cost too high. Schedule as a background job.']);
                        }
                        break;
                    }

                    $isMaterialized = !empty($config['materialized']);
                    if ($isMaterialized) {
                        if (!class_exists('\\SPPMod\\SPPReport\\Services\\SnapshotService')) {
                            require_once __DIR__ . '/services/SnapshotService.php';
                        }
                        $snapshotSvc = new \SPPMod\SPPReport\Services\SnapshotService();
                        if ($snapshotSvc->hasValidSnapshot($reportName)) {
                            $data = iterator_to_array($snapshotSvc->streamSnapshot($reportName));
                            $result = ['data' => $data, 'sql' => '/* Served from O(log N) Materialized Snapshot */'];
                        } else {
                            $result = $report->runReport($config);
                        }
                    } else {
                        $result = $report->runReport($config);
                    }

                    if ($isTurboStream) {
                        $this->stream('streams/report_update.php', [
                            'data' => $result['data'],
                            'target' => $_GET['stream_target'] ?? 'spp-configurator-container',
                            'action' => $_GET['stream_action'] ?? 'replace',
                            'interval' => $_GET['stream_interval'] ?? 5000,
                            'widget_type' => $_GET['widget_type'] ?? 'grid',
                            'widget_title' => $_GET['widget_title'] ?? 'Live Analytics Stream'
                        ]);
                        break;
                    } elseif ($isHtmx) {
                        $rawTemplate = $_POST['template_name'] ?? $_GET['template_name'] ?? 'report_preview.php';
                        $templateName = 'partials/' . basename($rawTemplate);
                        
                        echo $this->renderExternalPartial($templateName, [
                            'data' => $result['data'],
                            'sql' => $result['sql'],
                            'config_payload' => json_encode($config),
                            'org_name' => 'SPP Global Enterprise Solutions',
                            'confidentiality' => 'CONFIDENTIAL - FOR INTERNAL USE ONLY. DO NOT DISTRIBUTE.'
                        ]);
                        break;
                    }
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode([
                        'status' => 'success',
                        'data' => $result['data'],
                        'sql' => $result['sql']
                    ]);
                    break;

                case 'render_template':
                    $rawTemplate = $_POST['template_name'] ?? $_GET['template_name'] ?? 'report_template_dual_mode.php';
                    $templateName = 'partials/' . basename($rawTemplate);
                    $config['limit'] = 100;
                    $enforceRBAC($config);
                    $result = $report->runReport($config);
                    echo $this->renderExternalPartial($templateName, [
                        'data' => $result['data'],
                        'sql' => $result['sql'],
                        'config_payload' => json_encode($config),
                        'org_name' => 'SPP Global Enterprise Solutions',
                        'confidentiality' => 'CONFIDENTIAL - FOR INTERNAL USE ONLY. DO NOT DISTRIBUTE.'
                    ]);
                    break;

                case 'export_csv':
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    (new CsvExportDriver())->export($report, $config);
                    break;

                case 'export_xls':
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    if (file_exists(__DIR__ . '/services/PhpSpreadsheetDriver.php')) {
                        require_once __DIR__ . '/services/PhpSpreadsheetDriver.php';
                        (new \SPPMod\SPPReport\Services\PhpSpreadsheetDriver())->export($report, $config, $this);
                    } else {
                        (new ExcelExportDriver())->export($report, $config, $this);
                    }
                    break;

                case 'export_pdf':
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    if (file_exists(__DIR__ . '/services/ModernPdfDriver.php')) {
                        require_once __DIR__ . '/services/ModernPdfDriver.php';
                        (new \SPPMod\SPPReport\Services\ModernPdfDriver())->export($report, $config, $this);
                    } else {
                        (new PdfExportDriver())->export($report, $config, $this);
                    }
                    break;

                case 'export_json':
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    header('Content-Type: application/json');
                    echo '{"data":[';
                    $first = true;
                    foreach ($report->streamReport($config) as $row) {
                        if (!$first) echo ",";
                        echo json_encode($row);
                        $first = false;
                    }
                    echo "]}";
                    break;

                case 'export_xml':
                    $enforceRBAC($config);
                    $config['limit'] = 0;
                    header('Content-Type: application/xml');
                    $xml = new \XMLWriter();
                    $xml->openURI('php://output');
                    $xml->startDocument('1.0', 'UTF-8');
                    $xml->startElement('report');
                    foreach ($report->streamReport($config) as $row) {
                        $xml->startElement('row');
                        foreach ($row as $k => $v) {
                            $safeKey = is_numeric($k[0]) ? 'col_' . preg_replace('/[^a-zA-Z0-9_]/', '', $k) : preg_replace('/[^a-zA-Z0-9_]/', '', $k);
                            if (empty($safeKey)) $safeKey = 'column';
                            $xml->writeElement($safeKey, (string)$v);
                        }
                        $xml->endElement(); // row
                    }
                    $xml->endElement(); // report
                    $xml->endDocument();
                    $xml->flush();
                    break;

                case 'list':
                    if (!class_exists('\\SPPMod\\SPPReport\\Repositories\\ReportRepository')) {
                        require_once __DIR__ . '/Repositories/ReportRepository.php';
                    }
                    $repo = new \SPPMod\SPPReport\Repositories\ReportRepository();
                    $reports = array_column($repo->listAll(), 'name');
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'reports' => $reports]);
                    break;

                case 'load':
                    if (!class_exists('\\SPPMod\\SPPReport\\Repositories\\ReportRepository')) {
                        require_once __DIR__ . '/Repositories/ReportRepository.php';
                    }
                    $repo = new \SPPMod\SPPReport\Repositories\ReportRepository();
                    $data = $repo->load($_GET['name'] ?? '');
                    $enforceRBAC($data);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'config' => $data]);
                    break;

                case 'ai_analyze':
                    $enforceRBAC($config);
                    if (!class_exists('\\SPPMod\\SPPReport\\Services\\AiReportService')) {
                        require_once __DIR__ . '/Services/AiReportService.php';
                    }
                    $service = new \SPPMod\SPPReport\Services\AiReportService();
                    $analysis = $service->analyze($report, $config);

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'analysis' => $analysis]);
                    break;

                case 'save':
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $name = $config['report_name'] ?? '';
                    if (!$name) {
                        throw new \Exception("Report name is required.");
                    }
                    if (!class_exists('\\SPPMod\\SPPReport\\Repositories\\ReportRepository')) {
                        require_once __DIR__ . '/Repositories/ReportRepository.php';
                    }
                    $repo = new \SPPMod\SPPReport\Repositories\ReportRepository();
                    $repo->save($name, $config);

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Report saved successfully.']);
                    break;

                case 'list_versions':
                    $name = $_GET['name'] ?? '';
                    if (!$name) {
                        throw new \Exception("Report name is required.");
                    }
                    if (!class_exists('\\SPPMod\\SPPReport\\Repositories\\ReportRepository')) {
                        require_once __DIR__ . '/Repositories/ReportRepository.php';
                    }
                    $repo = new \SPPMod\SPPReport\Repositories\ReportRepository();
                    $versions = $repo->listVersions($name);

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'versions' => $versions]);
                    break;

                case 'restore_version':
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $name = $config['report_name'] ?? '';
                    $versionFile = $config['version_file'] ?? '';
                    if (!$name || !$versionFile) {
                        throw new \Exception("Name and version_file required.");
                    }
                    
                    if (!class_exists('\\SPPMod\\SPPReport\\Repositories\\ReportRepository')) {
                        require_once __DIR__ . '/Repositories/ReportRepository.php';
                    }
                    $repo = new \SPPMod\SPPReport\Repositories\ReportRepository();
                    $repo->restoreVersion($name, $versionFile);

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Version restored.']);
                    break;

                case 'list_templates':
                    $dir = __DIR__ . '/../../../../etc/sppreport_templates';
                    if (!is_dir($dir)) {
                        if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                        echo json_encode(['status' => 'success', 'templates' => []]);
                        break;
                    }
                    $files = glob($dir . '/*.html');
                    $templates = array_map(function ($f) {
                        return basename($f, '.html');
                    }, $files);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'templates' => $templates]);
                    break;

                case 'load_template':
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['name'] ?? '');
                    if (!$name) {
                        throw new \Exception("Invalid template name.");
                    }
                    $file = __DIR__ . '/../../../../etc/sppreport_templates/' . $name . '.html';
                    if (!file_exists($file)) {
                        throw new \Exception("Template not found.");
                    }

                    $html = file_get_contents($file);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'html' => $html]);
                    break;

                case 'save_template':
                    if (!$config) {
                        throw new \Exception("Invalid JSON payload.");
                    }
                    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $config['template_name'] ?? '');
                    $html = $config['html'] ?? '';
                    if (!$name) {
                        throw new \Exception("Template name is required.");
                    }

                    $dir = __DIR__ . '/../../../../etc/sppreport_templates';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $file = $dir . '/' . $name . '.html';
                    file_put_contents($file, $html);
                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => "Template '$name' saved successfully."]);
                    break;

                case 'ai_build':
                    $query = $_POST['query'] ?? $payloadObj['query'] ?? '';
                    
                    if (!class_exists('\\SPPMod\\SPPReport\\Services\\AiReportService')) {
                        require_once __DIR__ . '/Services/AiReportService.php';
                    }
                    $service = new \SPPMod\SPPReport\Services\AiReportService();
                    $configObj = $service->build($report, $query);

                    if ($isHtmx) {
                        echo $this->renderExternalPartial('partials/report_configurator.php', [
                            'driver' => $report->getDriver(),
                            'schema' => $report->getSchema(),
                            'table' => $configObj['table'] ?? '',
                            'columns' => $configObj['columns'] ?? []
                        ]);
                        break;
                    }

                    if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'config' => $configObj]);
                    break;

                default:
                    throw new \Exception("Unknown action: " . htmlspecialchars($action));
            }
        } catch (\Exception $e) {
            http_response_code(400);
            if ($isHtmx) {
                echo $this->renderExternalPartial('partials/report_preview.php', [
                    'error' => $e->getMessage()
                ]);
            } else {
                if (!$isHtmx && !$isTurboStream) header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
            }
        }
        exit;
    }

    private function parseLegacyYaml(string $file): array
    {
        if (function_exists('yaml_parse_file')) {
            return yaml_parse_file($file);
        }
        $symfonyYaml = __DIR__ . '/../../../../lib/vendor/autoload.php';
        if (file_exists($symfonyYaml)) {
            require_once $symfonyYaml;
            if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
                return \Symfony\Component\Yaml\Yaml::parseFile($file);
            }
        }
        $content = file_get_contents($file);
        if (preg_match("/json_dump:\s*'(.*)'$/m", $content, $m)) {
            $json = json_decode($m[1], true);
            if ($json) {
                return $json;
            }
        }
        throw new \Exception("Loading legacy YAML requires PECL yaml extension or Symfony Yaml.");
    }
}
