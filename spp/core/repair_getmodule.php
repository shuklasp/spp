<?php
$path = 'c:\projects\apache\school1\spp\core\class.module.php';
$content = file_get_contents($path);

$mangled = "    public static function getModule(string \$modname): \\SPP\\Module
    {
        \$modname = preg_replace('/[^a-zA-Z0-9_\\-]/', '', \$modname);
        \$modpath = \\SPP\\Registry::get('__mods=>' . \$modname);
        if (\$modpath === false) {
            throw new \\SPP\\SPPException(\"Module not registered: {\$modname}\");
        }
        \$modManifest = \$modpath . SPP_DS . 'module.xml';
        return new \\SPP\\Module(\$modManifest);
    }";

$fixed = "    public static function getModule(string \$modname): \\SPP\\Module
    {
        \$modname = preg_replace('/[^a-zA-Z0-9_\\-]/', '', \$modname);
        \$modpath = \\SPP\\Registry::get('__mods=>' . \$modname);
        if (\$modpath === false) {
            throw new \\SPP\\SPPException(\"Module not registered: {\$modname}\");
        }
        
        \$manifest = \$modpath . SPP_DS . 'module.yml';
        if (!file_exists(\$manifest)) \$manifest = \$modpath . SPP_DS . 'module.xml';
        
        if (!file_exists(\$manifest)) {
             throw new \\SPP\\SPPException(\"Module manifest not found for '{\$modname}' at {\$modpath}\");
        }
        
        return new \\SPP\\Module(\$manifest);
    }";

$content = str_replace($mangled, $fixed, $content);
file_put_contents($path, $content);
echo "Fixed getModule\n";
