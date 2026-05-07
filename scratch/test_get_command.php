<?php
$cmds = ['python', 'node', 'dotnet', 'go'];
foreach ($cmds as $cmd) {
    $out = [];
    $res = null;
    exec("powershell -Command \"(Get-Command $cmd).Source\" 2>&1", $out, $res);
    echo "$cmd: res=$res\n";
    print_r($out);
}
