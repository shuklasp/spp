<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\TestCase;
use SPPMod\SPPUX\SPPUX;

class SPPUXTest extends TestCase
{
    public function testComponentMarkupGeneration()
    {
        $props = ['title' => 'Test', 'count' => 5];
        $html = SPPUX::component('my-button', $props, 'testapp');
        
        $this->assertTrue(strpos($html, '<div') !== false, 'Component should be a div tag');
        $this->assertTrue(strpos($html, 'data-spp-component="1"') !== false, 'Should have component data attribute');
        $this->assertTrue(strpos($html, 'data-spp-path=') !== false, 'Should have path data attribute');
        $this->assertTrue(strpos($html, 'data-spp-props=') !== false, 'Should have props data attribute');
        $this->assertTrue(strpos($html, '&quot;title&quot;:&quot;Test&quot;') !== false, 'Should contain JSON encoded props');
        $this->assertTrue(strpos($html, 'my-button.js') !== false, 'Should contain component name in path');
    }

    public function testComponentWithSSRAndIsland()
    {
        $props = ['__ssr' => '<h1>Pre-rendered</h1>', '__island' => 'visible'];
        $html = SPPUX::component('my-island', $props, 'testapp');
        
        $this->assertTrue(strpos($html, '<h1>Pre-rendered</h1>') !== false, 'Should contain SSR content');
        $this->assertTrue(strpos($html, 'data-spp-island="visible"') !== false, 'Should contain island mode attribute');
    }

    public function testPaths()
    {
        $runtime = SPPUX::runtimePath();
        $this->assertTrue(strpos($runtime, 'sppux.js') !== false, 'Runtime path should contain sppux.js');
        
        $css = SPPUX::cssPath();
        $this->assertTrue(strpos($css, 'sppux.css') !== false, 'CSS path should contain sppux.css');
        
        $componentPath = SPPUX::componentPath('my-comp', 'testapp');
        $this->assertTrue(strpos($componentPath, 'testapp') !== false, 'Component path should contain appname');
        $this->assertTrue(strpos($componentPath, 'my-comp.js') !== false, 'Component path should contain component name');
    }
}
