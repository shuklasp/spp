<?php

$spp = file_get_contents('spp_original.php');

function extractCase($spp, $caseName) {
    $startToken = "case '$caseName':";
    $startIdx = strpos($spp, $startToken);
    if ($startIdx === false) return "";
    $startIdx += strlen($startToken);
    
    // Find the NEXT case statement on the same level (this is tricky with regex, but we know the exact next cases for our 4 broken commands)
    $nextTokens = [
        'ent:edit' => "case 'ent:delete':",
        'ent:manage' => "case 'ent:edit':",
        'view:page:add' => "case 'view:page:remove':",
        'view:service:add' => "case 'view:service:remove':"
    ];
    $endToken = $nextTokens[$caseName];
    $endIdx = strpos($spp, $endToken, $startIdx);
    
    // Fallback just in case
    if ($endIdx === false) {
        return "";
    }
    
    // Remove the trailing break;
    $body = substr($spp, $startIdx, $endIdx - $startIdx);
    $body = preg_replace('/break;\s*$/', '', $body);
    return trim($body);
}

$casesToFix = [
    'ent:edit' => 'EntEditCommand',
    'ent:manage' => 'EntManageCommand',
    'view:page:add' => 'ViewPageAddCommand',
    'view:service:add' => 'ViewServiceAddCommand'
];

foreach ($casesToFix as $caseName => $className) {
    $body = extractCase($spp, $caseName);
    if (empty($body)) {
        echo "Could not extract $caseName\n";
        continue;
    }
    
    // Fix paths and argv
    $body = str_replace("__DIR__ . '/sppinit.php'", "SPP_APP_DIR . '/spp/sppinit.php'", $body);
    $body = str_replace('$argv', '$args', $body);
    
    // Indent body
    $lines = explode("\n", $body);
    $indentedBody = implode("\n        ", $lines);
    
    $classContent = <<<PHP
<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class {$className} extends Command
{
    public function execute(array \$args): void
    {
        \$command = \$args[1] ?? '';
        {$indentedBody}
    }

    public function getName(): string
    {
        return '{$caseName}';
    }

    public function getDescription(): string
    {
        return 'Legacy port of {$caseName}';
    }
}
PHP;
    
    $outPath = __DIR__ . '/spp/commands/' . $className . '.php';
    file_put_contents($outPath, $classContent);
    echo "Fixed $className\n";
}
