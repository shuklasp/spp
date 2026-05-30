<?php
require_once "spp/core/class.command.php";
require_once "spp/commands/BaseMakeCommand.php";
require_once "spp/commands/MakeAppCommand.php";
$c = new \SPP\CLI\Commands\MakeAppCommand();
$ref = new \ReflectionClass($c);
$method = $ref->getMethod("execute");
$filename = $method->getFileName();
$start = $method->getStartLine() - 1;
$len = $method->getEndLine() - $start;
$source = implode("", array_slice(file($filename), $start, $len));
if (preg_match("/(?:echo|print)\s+[\"\'](Usage:\s*.*?)[\"\']/is", $source, $m)) {
    $helpText = trim($m[1]);
    $helpText = str_replace(['\n', '\r'], ["\n", ""], $helpText);
    echo $helpText;
} else {
    echo "NO MATCH";
}
