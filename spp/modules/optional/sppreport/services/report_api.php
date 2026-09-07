<?php
// Service route for SPPReport. Maps SPPAjax calls to the dedicated API handler.
require_once __DIR__ . '/../api.php';

$controller = new \SPPMod\SPPReport\ReportController();
$controller->handleRequest();
