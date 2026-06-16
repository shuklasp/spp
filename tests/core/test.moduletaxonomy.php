<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPP\Module;

class ModuleTaxonomyTest extends SPPTestCase
{
    private string $tempYaml;
    private string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = SPP_MODULES_DIR . '/spp/test_taxonomy';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        $this->tempYaml = $this->tempDir . '/module.yml';
    }

    public function tearDown(): void
    {
        if (file_exists($this->tempYaml)) {
            unlink($this->tempYaml);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testModuleTypeParsing()
    {
        $yaml = <<<YAML
module:
    name: test_taxonomy
    version: 1.0.0
    type: core
    compulsory: true
YAML;
        $file = $this->tempDir . '/module1.yml';
        file_put_contents($file, $yaml);

        $module = new Module($file);
        $this->assertEquals('core', $module->Type, 'Module type should be correctly parsed as core');
        $this->assertTrue($module->Compulsory, 'Module compulsory flag should be true');
        unlink($file);
    }

    public function testIsCompulsoryEvaluatesTrueForCoreAndCompulsory()
    {
        $yaml = <<<YAML
module:
    name: test_taxonomy
    version: 1.0.0
    type: core
    compulsory: true
YAML;
        $file = $this->tempDir . '/module2.yml';
        file_put_contents($file, $yaml);
        \SPP\Registry::register('__mods=>test_taxonomy', $this->tempDir);
        // Temporarily rename for this test so Module constructor finds it
        rename($file, $this->tempDir . '/module.yml');

        $isCompulsory = Module::isCompulsory('test_taxonomy');
        $this->assertTrue($isCompulsory, 'isCompulsory should return true for core+compulsory module');
        unlink($this->tempDir . '/module.yml');
    }

    public function testIsCompulsoryEvaluatesFalseForNonCore()
    {
        $yaml = <<<YAML
module:
    name: test_taxonomy_noncore
    version: 1.0.0
    type: contrib
    compulsory: true
YAML;
        $dir = $this->tempDir . '2';
        mkdir($dir, 0755, true);
        $file = $dir . '/module.yml';
        file_put_contents($file, $yaml);
        \SPP\Registry::register('__mods=>test_taxonomy_noncore', $dir);

        $isCompulsory = Module::isCompulsory('test_taxonomy_noncore');
        $this->assertFalse($isCompulsory, 'isCompulsory should return false if type is not core, even if compulsory is true');
        unlink($file);
        rmdir($dir);
    }

    public function testIsCompulsoryEvaluatesFalseForNonCompulsory()
    {
        $yaml = <<<YAML
module:
    name: test_taxonomy_noncompulsory
    version: 1.0.0
    type: core
    compulsory: false
YAML;
        $dir = $this->tempDir . '3';
        mkdir($dir, 0755, true);
        $file = $dir . '/module.yml';
        file_put_contents($file, $yaml);
        \SPP\Registry::register('__mods=>test_taxonomy_noncompulsory', $dir);

        $isCompulsory = Module::isCompulsory('test_taxonomy_noncompulsory');
        $this->assertFalse($isCompulsory, 'isCompulsory should return false if compulsory is false');
        unlink($file);
        rmdir($dir);
    }
}
