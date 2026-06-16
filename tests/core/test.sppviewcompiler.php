<?php

namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPPMod\SPPView\ViewCompiler;

class SPPViewCompilerTest extends SPPTestCase
{
    public function testCompilerTranslatesSppTrans()
    {
        $tempFile = SPP_BASE_DIR . '/var/cache/views/temp_test_view.html';
        if (!is_dir(dirname($tempFile))) {
            mkdir(dirname($tempFile), 0777, true);
        }
        
        file_put_contents($tempFile, '<div><spp-trans key="welcome_msg"></spp-trans></div>');
        
        $compiledFile = ViewCompiler::compile($tempFile);
        $compiledCode = file_get_contents($compiledFile);
        
        $this->assertTrue(strpos($compiledCode, '\SPPMod\SPPLang\SPPLang::getTranslation(\'welcome_msg\'') !== false);
        
        @unlink($tempFile);
        @unlink($compiledFile);
    }
}
