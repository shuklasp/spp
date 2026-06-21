<?php
echo "error_log is: " . ini_get('error_log') . "\n";
if (file_exists(ini_get('error_log'))) {
    echo "Tail of error_log:\n";
    echo shell_exec("tail -n 50 " . escapeshellarg(ini_get('error_log')));
} else {
    echo "No error log file found at that path.\n";
}
