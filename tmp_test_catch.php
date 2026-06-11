<?php
try {
    eval('class A { public function a() {} public function a() {} }');
    echo "NO ERROR\n";
} catch (\Throwable $e) {
    echo "CAUGHT: " . $e->getMessage() . "\n";
}
