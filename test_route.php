<?php
require 'c:/projects/apache/school1/spp/sppinit.php';
$q = 'theme-assets/lekhak_themes/glass_admin/theme.css';
$reflector = new ReflectionClass('\SPPMod\SPPRouter\SPPRouter');
$method = $reflector->getMethod('findPageInYaml');
$method->setAccessible(true);
$result = $method->invoke(null, $q, 'lekhak', null);
var_dump($result);
