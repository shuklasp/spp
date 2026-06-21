<?php
$content = file_get_contents('spp/core/class.module.php');
$inject = <<<PHP
                      if (\$isValid) {
                          foreach (\$compiled as \$name => \$data) {
                              \$modManifest = \$data['path'] . SPP_DS . 'module.yml';
                              if (!file_exists(\$modManifest)) \$modManifest = \$data['path'] . SPP_DS . 'module.xml';
                              if (file_exists(\$modManifest) && filemtime(\$modManifest) > \$meta['manifest_mtime']) {
                                  error_log("Phase 1 INVALIDATED by " . \$modManifest);
                                  \$isValid = false; break;
                              }
                          }
                      } else {
                          error_log("Phase 1 INVALIDATED by manifest");
                      }
PHP;
$content = preg_replace('/if \(\$isValid\) \{\s*foreach \(\$compiled as \$name => \$data\) \{\s*\$modManifest = \$data\[\'path\'\] \. SPP_DS \. \'module\.yml\';\s*if \(\!file_exists\(\$modManifest\)\) \$modManifest = \$data\[\'path\'\] \. SPP_DS \. \'module\.xml\';\s*if \(file_exists\(\$modManifest\) && filemtime\(\$modManifest\) > \$meta\[\'manifest_mtime\'\]\) \{\s*\$isValid = false; break;\s*\}\s*\}\s*\}/', $inject, $content);
file_put_contents('spp/core/class.module.php', $content);
echo "Injected reason.\n";
