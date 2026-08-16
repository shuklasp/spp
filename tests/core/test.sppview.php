<?php
namespace SPP\Tests\Core;

require_once SPP_APP_DIR . '/spp/modules/spp/sppview/classes.htmlelements.php';
require_once SPP_APP_DIR . '/spp/modules/spp/sppview/class.spphtmltable.php';

use SPPMod\SPPView\ViewTag;
use SPPMod\Parikshak\SPPTestCase;
use SPPMod\SppView\ViewCompiler;
use SPPMod\SppView\SppView;
use SPPMod\SPPView\HtmlElements\SPP_HTML_Ul;
use SPPMod\SPPView\SPP_HTML_Table;
use SPPMod\SPPView\SPP_HTML_TableRow;
use SPPMod\SPPView\SPP_HTML_TableField;

/**
 * Tests for the SPP View Engine.
 *
 * @group Core
 * @group View
 */
class SPPViewTest extends SPPTestCase
{
    public function testViewTagGeneration()
    {
        $div = new ViewTag('div', 'test-div');
        $div->setAttribute('id', 'test-div');
        $div->addClass('container');
        $div->setMatterText('Hello World');
        
        $html = $div->getHTML();
        
        $this->assertTrue(strpos($html, '<div') !== false, 'HTML should start with div tag');
        $this->assertTrue(strpos($html, 'id="test-div"') !== false, 'HTML should contain id attribute');
        $this->assertTrue(strpos($html, 'container') !== false, 'HTML should contain class attribute');
        $this->assertTrue(strpos($html, 'Hello World') !== false, 'HTML should contain text content');
        $this->assertTrue(strpos($html, '</div>') !== false, 'HTML should end with div closing tag');
    }

    public function testUlGeneration()
    {
        $ul = new SPP_HTML_Ul('test-ul');
        $ul->addItem('Item 1');
        $ul->addItem('Item 2');
        
        $html = $ul->getHTML();
        
        $this->assertTrue(strpos($html, '<ul') !== false, 'HTML should contain ul tag');
        $this->assertTrue(strpos($html, 'Item 1') !== false, 'HTML should contain Item 1');
        $this->assertTrue(strpos($html, 'Item 2') !== false, 'HTML should contain Item 2');
    }

    public function testTableGeneration()
    {
        $table = new SPP_HTML_Table('test-table');
        
        // Header
        $headRow = new SPP_HTML_TableRow('hrow1');
        $headFld1 = new SPP_HTML_TableField('hfld1', true);
        $headFld1->setContent('ID');
        $headRow->addField($headFld1);
        $table->addHeaderRow($headRow);
        
        // Body
        $bodyRow = new SPP_HTML_TableRow('brow1');
        $bodyFld1 = new SPP_HTML_TableField('bfld1', false);
        $bodyFld1->setContent('1');
        $bodyRow->addField($bodyFld1);
        $table->addRow($bodyRow);
        
        $html = $table->getHTML();
        
        $this->assertTrue(strpos($html, '<table') !== false, 'HTML should contain table tag');
        $this->assertTrue(strpos($html, '<th') !== false, 'HTML should contain th header tag');
        $this->assertTrue(strpos($html, 'ID') !== false, 'HTML should contain ID');
        $this->assertTrue(strpos($html, '<td') !== false, 'HTML should contain td cell tag');
        $this->assertTrue(strpos($html, '1') !== false, 'HTML should contain 1');
        $this->assertTrue(strpos($html, '</table>') !== false, 'HTML should end with table closing tag');
    }
}
