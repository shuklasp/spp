<?php
// Test app_init script for autodemo
$logMsg = date('[Y-m-d H:i:s]') . " [AUTODEMO] init.php executed. Context: " . \SPP\Scheduler::getContext() . "\n";
@file_put_contents(SPP_LOG_DIR . '/spp_debug.log', $logMsg, FILE_APPEND);
