<?php
require_once __DIR__ . '/../sppinit.php';
try {
    \SPP\Scheduler::regProc(new \SPP\App('spp_docs'));
} catch (\Exception $e) {}
\SPP\Scheduler::setContext('spp_docs');

$alias = '1.0/tutorial/intro';
echo "Loading node for alias: $alias\n";

$node = new \SPPMod\Lekhak\Core\LekhakNode();
try {
    $node->loadBy('alias', $alias);
    echo "SUCCESS: Found node ID {$node->id}\n";
    echo "Title: {$node->title}\n";
} catch (\Exception $e) {
    echo "FAILURE: " . $e->getMessage() . "\n";
}
