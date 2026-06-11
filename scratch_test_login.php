<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
$_SERVER['SCRIPT_NAME'] = '/spp/admin/api.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'login';
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';
$_REQUEST = $_POST;
require 'spp/admin/api.php';
