<?php

$path = 'c:\projects\apache\school1\spp\core\class.module.php';
$lines = file($path);

$start = 626;
$end = 646;

$newLines = [
    "    public static function getModule(string \$modname): \\SPP\\Module\n",
    "    {\n",
    "        \$modname = preg_replace('/[^a-zA-Z0-9_\\-]/', '', \$modname);\n",
    "        \$modpath = \\SPP\\Registry::get('__mods=>' . \$modname);\n",
    "        if (\$modpath === false) {\n",
    "            throw new \\SPP\\SPPException(\"Module not registered: {\$modname}\");\n",
    "        }\n",
    "        \n",
    "        \$manifest = \$modpath . SPP_DS . 'module.yml';\n",
    "        if (!file_exists(\$manifest)) \$manifest = \$modpath . SPP_DS . 'module.xml';\n",
    "        \n",
    "        if (!file_exists(\$manifest)) {\n",
    "             throw new \\SPP\\SPPException(\"Module manifest not found for '{\$modname}' at {\$modpath}\");\n",
    "        }\n",
    "        \n",
    "        return new \\SPP\\Module(\$manifest);\n",
    "    }\n"
];

array_splice($lines, $start - 1, $end - $start + 1, $newLines);

file_put_contents($path, implode("", $lines));
echo "Cleaned up getModule successfully\n";
