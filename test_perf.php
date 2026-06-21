<?php
$start = microtime(true);
$logs = [];
function add_log($msg) {
    global $start, $logs;
    $logs[] = number_format(microtime(true) - $start, 4) . "s: $msg";
}

add_log("Start");

require_once('vendor/autoload.php');
add_log("After autoload");

require_once('spp/sppinit.php');
add_log("After sppinit");

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/school1/index.php';
$_SERVER['REQUEST_URI'] = '/school1/lekhak/admin/landing';
$_GET['q'] = 'lekhak/admin/landing';

$context = \SPP\Scheduler::getContext();
if ($context !== '') {
    $baseUrl = trim(\SPP\App::getAppConf('base_url') ?: '/' . $context, '/');
    $qPath = trim($_GET['q'], '/');
    if ($baseUrl !== '' && (str_starts_with($qPath, $baseUrl . '/') || $qPath === $baseUrl)) {
        $_GET['q'] = ltrim(substr($qPath, strlen($baseUrl)), '/');
    }
}
add_log("After context");

require_once('global.php');
add_log("After global");

register_shutdown_function(function() use (&$logs) {
    add_log("Shutdown");
    file_put_contents('perf.log', implode("\n", $logs) . "\n");
});

\SPP\Core\MiddlewareKernel::run(function($request) {
    global $logs;
    add_log("Middleware kernel started closure");

    $context = \SPP\Scheduler::getContext() ?: 'default';
    \SPP\Scheduler::setContext($context);
    
    add_log("Before activeProc");
    $activeProc = \SPP\Scheduler::getActiveProc();
    if (method_exists($activeProc, 'handle')) {
        $activeProc->handle($request);
        return;
    }
    
    add_log("Before ViewPage::processForms");
    \SPPMod\SPPView\ViewPage::processForms();
    add_log("Before ViewPage::showPage");
    \SPPMod\SPPView\ViewPage::showPage();
});
