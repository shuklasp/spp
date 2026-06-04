<?php
require 'c:/projects/apache/school1/spp/sppinit.php';
require 'C:/projects/apache/school1/src/lekhak/services/class.testjavaai.php';
$ai = new \App\Lekhak\Services\TestJavaAi();
echo json_encode($ai->handle(['msg' => 'hello from php to java']));
