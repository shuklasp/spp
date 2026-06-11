<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
$_SERVER['SCRIPT_NAME'] = '/school1/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['__api'] = '1';
$_GET['entity'] = 'docs';
$_REQUEST = $_GET;
require 'index.php';
