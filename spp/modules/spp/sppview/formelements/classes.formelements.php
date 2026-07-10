<?php

namespace SPPMod\SPPView;

//require_once 'class.sppformelement.php';

class SPPViewForm_Input extends SPPViewForm_Element
{
    public function __construct($ename, $type = 'text')
    {
        parent::__construct($ename);
        $this->isemptyflag = true;
        $this->tagname = 'input';
        $this->setAttribute('type', $type);
    }
}

class SPPViewForm_Input_Text extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'text');
    }
}

class SPPViewForm_Input_Password extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'password');
    }
}

class SPPViewForm_Input_Email extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'email');
    }
}

class SPPViewForm_Input_Number extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'number');
    }
}

class SPPViewForm_Input_Tel extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'tel');
    }
}

class SPPViewForm_Input_Range extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'range');
    }
}

class SPPViewForm_Input_Color extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'color');
    }
}

class SPPViewForm_Input_Submit extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'submit');
        $this->setGrouped(false);
    }
}

class SPPViewForm_Input_Button extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'button');
        $this->setGrouped(false);
    }
}

class SPPViewForm_TextArea extends SPPViewForm_Element
{
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        $this->tagname = 'textarea';
        $this->attrlist = array_merge($this->attrlist, ['cols', 'rows', 'disabled', 'name', 'readonly']);
    }
}

class SPPViewForm_Button extends SPPViewForm_Element
{
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        $this->tagname = 'button';
        $this->attrlist = array_merge($this->attrlist, ['disabled', 'name', 'type', 'value']);
    }
}

class SPPViewForm_Option extends SPPViewForm_Element
{
    private $opttext;

    public function __construct($disptext, $optvalue, $ename = '')
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        $this->tagname = 'option';
        $this->attrlist = ['disabled', 'label', 'selected', 'value'];
        $this->opttext = $disptext;
        $this->setMatterText($disptext);
        $this->setAttribute('value', $optvalue);
        $this->setGrouped(false);
    }

    public function render()
    {
        $htm = parent::render();
        $htm .= $this->opttext;
        $htm .= '</option>';
        return $htm;
    }

}

class SPPViewForm_Select extends SPPViewForm_Element
{
    private $options;
    private $optkey = 0;
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        $this->tagname = 'select';
        $this->attrlist = ['disabled', 'multiple', 'name', 'size'];
        $this->addOption('Select', '', true);
    }

    public function setAttribute($name, $val)
    {
        if ($name === 'value') {
            $selectedValues = is_array($val) ? $val : [$val];
            if (!empty($this->options)) {
                foreach ($this->options as $idx => $optArr) {
                    $opt = $optArr[0];
                    if (in_array($opt->getAttribute('value'), $selectedValues)) {
                        $opt->setAttribute('selected', 'selected');
                    } else {
                        $opt->removeAttribute('selected');
                    }
                }
            }
            if (is_array($val)) {
                return true;
            }
        }
        return parent::setAttribute($name, $val);
    }

    public function addOption($disptext, $optvalue, $selected = false, $ename = '', $optgroup = '')
    {
        $this->options[$this->optkey++] = [new SPPViewForm_Option($disptext, $optvalue, $ename = ''), $optgroup];
        if ($selected) {
            $this->options[$this->optkey - 1][0]->setAttribute('selected', 'true');
        }
    }

    public function readFromArray(array $arr)
    {
        parent::readFromArray($arr);
        if (array_key_exists('options', $arr)) {
            foreach ($arr['options'] as $options) {
                if (array_key_exists('option', $options)) {
                    foreach ($options['option'] as $option) {
                        $disptext = (array_key_exists('text', $option)) ? $option['text'] : '';
                        $optvalue = (array_key_exists('value', $option)) ? $option['value'] : '';
                        $ename = (array_key_exists('name', $option)) ? $option['name'] : '';
                        $selected = (array_key_exists('selected', $option)) ? $option['selected'] : '';
                        $optgroup = (array_key_exists('optgroup', $option)) ? $option['optgroup'] : '';
                        $this->addOption($disptext, $optvalue, $selected, $ename, $optgroup);
                    }
                }
            }
        }
    }

    public function renderRaw(): string
    {
        $prevoptgroup = '';
        $htm = $this->getStart();
        foreach ($this->options as $opt) {
            if ($opt[1] != $prevoptgroup) {
                if ($prevoptgroup != '') {
                    $htm .= '</optgroup>';
                }
                if ($opt[1] != '') {
                    $htm .= '<optgroup label="' . $opt[1] . '">';
                }
            }
            $prevoptgroup = $opt[1];
            $htm .= $opt[0]->getHTML();
        }
        if ($prevoptgroup != '') {
            $htm .= '</optgroup>';
        }
        $htm .= $this->getEnd();
        return $htm;
    }
}

class SPPViewForm_SQLDropDown extends SPPViewForm_Select
{
    public function __construct($ename, $sql, $optdispfld, $optvalfld, $values = [], $defval = '', $optgrpfld = '')
    {
        parent::__construct($ename);
        $db = new \SPPMod\SPPDB\SPPDB();
        $result = $db->execute_query($sql, $values);
        foreach ($result as $res) {
            if ($res[$optvalfld] == $defval) {
                parent::addOption($res[$optdispfld], $res[$optvalfld], true, $res[$optvalfld], $res[$optgrpfld]);
            } else {
                parent::addOption($res[$optdispfld], $res[$optvalfld], false, $res[$optvalfld], $res[$optgrpfld]);
            }
        }
    }
}

class SPPViewForm_Input_Radio extends SPPViewForm_Input
{
    private $options = [];
    public function __construct($ename, $val = '', $label = '', $checked = false)
    {
        parent::__construct($ename);
        if ($val != '') {
            $this->addOption($val, $label, $checked);
        }
    }

    public function addOption($optval, $label = '', $checked = false)
    {
        $this->options[] = ['value' => $optval, 'label' => $label ?: $optval, 'checked' => $checked];
    }

    public function renderRaw(): string
    {
        $name = $this->getAttribute('name');
        $id = $this->getAttribute('id');
        $htm = '<div class="spp-radio-group" style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">';
        foreach ($this->options as $opt) {
            $checkedAttr = $opt['checked'] ? 'checked="checked"' : '';
            $optId = $id . '_' . $opt['value'];
            $htm .= "
                <label class=\"spp-radio-label\" style=\"display: flex; align-items: center; gap: 10px; cursor: pointer;\">
                    <input type=\"radio\" name=\"{$name}\" id=\"{$optId}\" value=\"{$opt['value']}\" {$checkedAttr} style=\"width: 18px; height: 18px; accent-color: var(--accent-primary);\">
                    <span style=\"color: var(--text-main); font-size: 0.9rem;\">{$opt['label']}</span>
                </label>";
        }
        $htm .= '</div>';
        return $htm;
    }
}

class SPPViewForm_Input_Checkbox extends SPPViewForm_Input
{
    protected $options = [];
    public function __construct($ename, $val = '', $label = '', $checked = false)
    {
        parent::__construct($ename);
        $this->setAttribute('type', 'checkbox');
        if ($val != '') {
            $this->addOption($val, $label, $checked);
        }
    }

    public function addOption($optval, $label = '', $checked = false)
    {
        $this->options[] = ['value' => $optval, 'label' => $label ?: $optval, 'checked' => $checked];
    }

    public function renderRaw(): string
    {
        $name = $this->getAttribute('name');
        $id = $this->getAttribute('id');
        $htm = '<div class="spp-checkbox-group" style="display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">';
        foreach ($this->options as $opt) {
            $checkedAttr = $opt['checked'] ? 'checked="checked"' : '';
            $optId = $id . '_' . $opt['value'];
            $htm .= "
                <label class=\"spp-checkbox-label\" style=\"display: flex; align-items: center; gap: 10px; cursor: pointer;\">
                    <input type=\"checkbox\" name=\"{$name}\" id=\"{$optId}\" value=\"{$opt['value']}\" {$checkedAttr} style=\"width: 18px; height: 18px; accent-color: var(--accent-primary);\">
                    <span style=\"color: var(--text-main); font-size: 0.9rem;\">{$opt['label']}</span>
                </label>";
        }
        $htm .= '</div>';
        return $htm;
    }
}

class SPPViewForm_File extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename, 'file');
        $this->attrlist = array_merge($this->attrlist, ['multiple', 'accept']);
        $this->addClass('spp-file-enhanced');
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/sppfile.js');
        return parent::getHTML();
    }
}

class SPPViewForm_InputMask extends SPPViewForm_Input
{
    public function __construct($ename, $mask = '')
    {
        parent::__construct($ename, 'text');
        $this->setAttribute('data-mask', $mask);
        $this->addClass('spp-masked-input');
    }
}

class SPPViewForm_Repeater extends SPPViewForm_Element
{
    private array $templateFields = [];
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->tagname = 'div';
        $this->setGrouped(false);
        $this->addClass('spp-repeater');
        $this->setAttribute('data-repeater-name', $ename);
    }
    public function setTemplate(array $fields)
    {
        $this->templateFields = $fields;
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/spprepeater.js');
        $html = '<div ' . $this->getAttributesHTML() . '>';
        $html .= '<div class="spp-repeater-list"></div>';
        $html .= '<button type="button" class="spp-repeater-add btn-add-row">Add Another</button>';

        // Hidden template for JS to clone
        $html .= '<template class="spp-repeater-template">';
        $html .= '<div class="spp-repeater-item" style="border: 1px solid #eee; padding: 15px; margin-bottom: 10px; position: relative;">';
        $html .= '<button type="button" class="spp-repeater-remove" style="position: absolute; top: 5px; right: 5px;">&times;</button>';
        foreach ($this->templateFields as $f) {
            $html .= $f->getHTML();
        }
        $html .= '</div>';
        $html .= '</template>';

        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_Toggle extends SPPViewForm_Input_Checkbox
{
    public function renderRaw(): string
    {
        $name = $this->getAttribute('name');
        $id = $this->getAttribute('id');
        $checked = false;
        foreach ($this->options as $opt) {
            if ($opt['checked']) {
                $checked = true;
            }
        }
        $checkedAttr = $checked ? 'checked' : '';
        return "
            <div class=\"toggle-container\" style=\"display: inline-flex; align-items: center; gap: 10px;\">
                <label class=\"toggle-switch\" id=\"toggle_wrapper_{$id}\">
                    <input type=\"checkbox\" name=\"{$name}\" id=\"{$id}\" value=\"1\" {$checkedAttr} class=\"spp-element spp-toggle\">
                    <span class=\"toggle-slider\"></span>
                </label>
            </div>
        ";
    }
}

class SPPViewForm_DatePicker extends SPPViewForm_Input
{
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->setAttribute('type', 'date');
        $this->addClass('spp-datepicker sppux-date-enhanced');
    }
}

class SPPViewForm_MasterGrid extends SPPViewForm_Element
{
    private array $columns = [];
    private array $data = [];
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
    }
    public function setColumns(array $cols)
    {
        $this->columns = $cols;
    }
    public function setData(array $data)
    {
        $this->data = $data;
    }
    public function render()
    {
        $props = ["columns" => $this->columns, "data" => $this->data, "onUpdate" => $this->getAttribute("onUpdate")];
        return \SPPMod\Drishyam\SPPUX::component("MasterGrid", $props);
    }
}

class SPPViewForm_Editor extends SPPViewForm_Element
{
    public function render()
    {
        $props = ["name" => $this->getAttribute("name"), "value" => $this->getAttribute("value"), "placeholder" => $this->getAttribute("placeholder"), "height" => $this->getAttribute("height")];
        return \SPPMod\Drishyam\SPPUX::component("Editor", $props, "drishyam");
    }
}

class SPPViewForm_Chart extends SPPViewForm_Element
{
    private array $data = [];
    private string $type = "bar";
    public function setChartData(array $data)
    {
        $this->data = $data;
    }
    public function setChartType(string $type)
    {
        $this->type = $type;
    }
    public function render()
    {
        $props = ["type" => $this->type, "data" => $this->data, "options" => $this->getAttribute("options")];
        return \SPPMod\Drishyam\SPPUX::component("Chart", $props, "drishyam");
    }
}

class SPPViewForm_CodeEditor extends SPPViewForm_Element
{
    public function render()
    {
        $props = ["name" => $this->getAttribute("name"), "value" => $this->getAttribute("value"), "language" => $this->getAttribute("language"), "height" => $this->getAttribute("height")];
        return \SPPMod\Drishyam\SPPUX::component("Code", $props, "drishyam");
    }
}

class SPPViewForm_Map extends SPPViewForm_Element
{
    public function render()
    {
        $props = ["height" => $this->getAttribute("height"), "center" => $this->getAttribute("center"), "zoom" => $this->getAttribute("zoom"), "markers" => $this->getAttribute("markers")];
        return \SPPMod\Drishyam\SPPUX::component("Map", $props, "drishyam");
    }
}

class SPPViewForm_AdvancedCalendar extends SPPViewForm_Element
{
    public function render()
    {
        $props = ["name" => $this->getAttribute("name"), "value" => $this->getAttribute("value"), "mode" => $this->getAttribute("mode"), "enableTime" => $this->getAttribute("enableTime")];
        return \SPPMod\Drishyam\SPPUX::component("Calendar", $props, "drishyam");
    }
}

class SPPViewForm_Sortable extends SPPViewForm_Element
{
    public function render()
    {
        $props = ["items" => $this->getAttribute("items"), "onSort" => $this->getAttribute("onSort")];
        return \SPPMod\Drishyam\SPPUX::component("Sortable", $props, "drishyam");
    }
}

class SPPViewForm_Autocomplete extends SPPViewForm_Select
{
    public function __construct($ename, $sourceUrl = '')
    {
        parent::__construct($ename);
        $this->addClass('spp-autocomplete');
        if ($sourceUrl) {
            $this->setAttribute('data-source', $sourceUrl);
        }
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/sppautocomplete.js');
        return parent::getHTML();
    }
}

class SPPViewForm_Signature extends SPPViewForm_Element
{
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->tagname = 'div';
        $this->addClass('spp-signature-pad');
        $this->setGrouped(true);
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/sppsignature.js');
        $id = $this->getAttribute('id');
        $html = '<div ' . $this->getAttributesHTML() . ' style="border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 10px; background: var(--input-bg);">';
        $html .= '<canvas width="400" height="200" style="width: 100%; height: 200px; cursor: crosshair; touch-action: none; border-bottom: 1px solid #eee;"></canvas>';
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px;">';
        $html .= '<span style="font-size: 0.7rem; color: #999;">Sign inside the box</span>';
        $html .= '<button type="button" class="btn-clear-sig" style="background: none; border: none; color: #d9534f; cursor: pointer; font-size: 0.75rem;">Clear</button>';
        $html .= '</div>';
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '_val" value="">';
        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_Tags extends SPPViewForm_Element
{
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->tagname = 'div';
        $this->addClass('spp-tag-input-container');
        $this->setGrouped(true);
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/spptags.js');
        $id = $this->getAttribute('id');
        $val = $this->getAttribute('value') ?: '';

        $html = '<div ' . $this->getAttributesHTML() . ' style="border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 5px; background: var(--input-bg); display: flex; flex-wrap: wrap; align-items: center; gap: 5px;">';
        $html .= '<div class="spp-tag-list" style="display: contents;"></div>';
        $html .= '<input type="text" placeholder="Add tag..." style="border: none; outline: none; flex: 1; min-width: 100px; font-size: 0.9rem; padding: 5px;">';
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '" value="' . $val . '">';
        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_OTP extends SPPViewForm_Element
{
    private int $digits = 6;
    public function __construct($ename, $digits = 6)
    {
        parent::__construct($ename);
        $this->digits = $digits;
        $this->tagname = 'div';
        $this->addClass('spp-otp-container');
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/sppotp.js');
        $id = $this->getAttribute('id');
        $html = '<div ' . $this->getAttributesHTML() . ' style="display: flex; gap: 8px;">';
        for ($i = 0; $i < $this->digits; $i++) {
            $html .= '<input type="text" class="otp-digit" maxlength="1" style="width: 40px; height: 50px; text-align: center; font-size: 1.5rem; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-main); border-radius: var(--radius-md); outline: none;">';
        }
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '" value="">';
        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_Rating extends SPPViewForm_Element
{
    private int $max = 5;
    public function __construct($ename, $max = 5)
    {
        parent::__construct($ename);
        $this->max = $max;
        $this->tagname = 'div';
        $this->addClass('spp-rating-container');
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/spprating.js');
        $id = $this->getAttribute('id');
        $val = $this->getAttribute('value') ?: 0;
        $html = '<div ' . $this->getAttributesHTML() . ' style="display: flex; gap: 5px; font-size: 1.5rem; cursor: pointer;">';
        for ($i = 1; $i <= $this->max; $i++) {
            $html .= '<span class="star-icon" style="color: #ccc;">★</span>';
        }
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '" value="' . $val . '">';
        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_Range extends SPPViewForm_Element
{
    private int $min = 0;
    private int $max = 100;
    public function __construct($ename, $min = 0, $max = 100)
    {
        parent::__construct($ename);
        $this->min = $min;
        $this->max = $max;
        $this->tagname = 'div';
        $this->addClass('spp-range-container');
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/spprange.js');
        $id = $this->getAttribute('id');
        $val = $this->getAttribute('value') ?: $this->min . '-' . $this->max;
        $parts = explode('-', $val);
        $vMin = $parts[0] ?? $this->min;
        $vMax = $parts[1] ?? $this->max;

        $html = '<div ' . $this->getAttributesHTML() . ' style="position: relative; padding-top: 20px;">';
        $html .= '<div class="range-display" style="position: absolute; top: 0; right: 0; font-size: 0.8rem; font-weight: bold;">' . $vMin . ' - ' . $vMax . '</div>';
        $html .= '<input type="range" class="range-min" min="' . $this->min . '" max="' . $this->max . '" value="' . $vMin . '" style="width: 100%;">';
        $html .= '<input type="range" class="range-max" min="' . $this->min . '" max="' . $this->max . '" value="' . $vMax . '" style="width: 100%; margin-top: -10px;">';
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '" value="' . $val . '">';
        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_Cropper extends SPPViewForm_Element
{
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->tagname = 'div';
        $this->addClass('spp-cropper-container');
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/sppcropper.js');
        $id = $this->getAttribute('id');
        $html = '<div ' . $this->getAttributesHTML() . ' style="border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
        $html .= '<input type="file" accept="image/*" style="width: 100%; margin-bottom: 10px;">';
        $html .= '<img class="crop-preview" style="display: none; width: 150px; height: 150px; object-fit: cover; border-radius: 4px; border: 1px solid #eee; margin-bottom: 10px;">';
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '" value="">';
        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_TreeSelect extends SPPViewForm_Element
{
    private array $data = [];
    public function __construct($ename, array $data = [])
    {
        parent::__construct($ename);
        $this->data = $data;
        $this->tagname = 'div';
        $this->addClass('spp-tree-select');
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/spptreeselect.js');
        $id = $this->getAttribute('id');
        $html = '<div ' . $this->getAttributesHTML() . ' style="position: relative;">';
        $html .= '<button type="button" class="tree-toggle" style="width: 100%; padding: 8px; text-align: left; background: var(--input-bg); color: var(--text-main); border: 1px solid var(--glass-border); border-radius: var(--radius-md); cursor: pointer;">Select Option...</button>';
        $html .= '<div class="tree-list" style="display: none; position: absolute; z-index: 100; width: 100%; background: var(--panel-bg-solid); color: var(--text-main); border: 1px solid var(--glass-border); border-top: none; max-height: 200px; overflow-y: auto; padding: 10px;">';
        $html .= $this->renderTree($this->data);
        $html .= '</div>';
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '" value="">';
        $html .= '</div>';
        return $html;
    }
    private function renderTree($data)
    {
        $html = '<ul style="list-style: none; padding-left: 15px; margin: 0;">';
        foreach ($data as $item) {
            $html .= '<li style="margin: 4px 0;">';
            if (!empty($item['children'])) {
                $html .= '<span style="font-weight: bold;">📁 ' . $item['label'] . '</span>';
                $html .= $this->renderTree($item['children']);
            } else {
                $html .= '<span class="tree-node" data-value="' . $item['value'] . '" style="cursor: pointer; color: #007bff;">📄 ' . $item['label'] . '</span>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}

class SPPViewForm_DualList extends SPPViewForm_Element
{
    private array $options = [];
    public function __construct($ename, array $options = [])
    {
        parent::__construct($ename);
        $this->options = $options;
        $this->tagname = 'div';
        $this->addClass('spp-dual-list');
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/sppduallist.js');
        $id = $this->getAttribute('id');
        $html = '<div ' . $this->getAttributesHTML() . ' style="display: flex; gap: 10px; align-items: center;">';
        $html .= '<div style="flex: 1;"><label style="font-size: 0.7rem;">Available</label><select multiple class="list-available" style="width: 100%; height: 150px; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-main); border-radius: var(--radius-md);">';
        foreach ($this->options as $val => $label) {
            $html .= '<option value="' . $val . '">' . $label . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div style="display: flex; flex-direction: column; gap: 5px;"><button type="button" class="btn-add"> &raquo; </button><button type="button" class="btn-remove"> &laquo; </button></div>';
        $html .= '<div style="flex: 1;"><label style="font-size: 0.7rem;">Selected</label><select multiple class="list-selected" style="width: 100%; height: 150px; border: 1px solid var(--glass-border); background: var(--input-bg); color: var(--text-main); border-radius: var(--radius-md);"></select></div>';
        $html .= '<input type="hidden" name="' . $this->getAttribute('name') . '" id="' . $id . '" value="">';
        $html .= '</div>';
        return $html;
    }
}

class SPPViewForm_Portability extends SPPViewForm_Element
{
    public function __construct($ename = 'portability')
    {
        parent::__construct($ename);
        $this->tagname = 'div';
        $this->addClass('spp-portability-container');
        $this->setGrouped(false);
    }
    public function renderRaw(): string
    {
        ViewPage::addJsIncludeFile('res/js/spportability.js');
        $html = '<div ' . $this->getAttributesHTML() . ' style="display: flex; gap: 10px; margin-top: 15px; padding: 10px; background: var(--btn-soft-bg); border: 1px dashed var(--glass-border); border-radius: var(--radius-md);">';
        $html .= '<button type="button" class="btn-export-json" style="font-size: 0.8rem; padding: 5px 10px; cursor: pointer;">Download JSON Draft</button>';
        $html .= '<button type="button" class="btn-import-json" style="font-size: 0.8rem; padding: 5px 10px; cursor: pointer;">Upload JSON Draft</button>';
        $html .= '<input type="file" class="import-file-input" accept=".json" style="display: none;">';
        $html .= '<div style="font-size: 0.7rem; color: #888; align-self: center;">Portability: Move your data between devices</div>';
        $html .= '</div>';
        return $html;
    }
}

// Class Aliases
class SPPText extends SPPViewForm_Input_Text
{
}
class SPPPassword extends SPPViewForm_Input_Password
{
}
class SPPSubmit extends SPPViewForm_Input_Submit
{
}
class SPPTextArea extends SPPViewForm_TextArea
{
}
class SPPButton extends SPPViewForm_Button
{
}
class SPPSelect extends SPPViewForm_Select
{
}
class SPPRadio extends SPPViewForm_Input_Radio
{
}
class SPPCheckbox extends SPPViewForm_Input_Checkbox
{
}
class SPPDatePicker extends SPPViewForm_DatePicker
{
}
class SPPEmail extends SPPViewForm_Input_Email
{
}
class SPPNumber extends SPPViewForm_Input_Number
{
}
class SPPFile extends SPPViewForm_File
{
}
class SPPMask extends SPPViewForm_InputMask
{
}
class SPPRepeater extends SPPViewForm_Repeater
{
}
class SPPAutocomplete extends SPPViewForm_Autocomplete
{
}
class SPPSignature extends SPPViewForm_Signature
{
}
class SPPTags extends SPPViewForm_Tags
{
}
class SPPOTP extends SPPViewForm_OTP
{
}
class SPPRating extends SPPViewForm_Rating
{
}
class SPPRange extends SPPViewForm_Range
{
}
class SPPCropper extends SPPViewForm_Cropper
{
}
class SPPTreeSelect extends SPPViewForm_TreeSelect
{
}
class SPPDualList extends SPPViewForm_DualList
{
}
class SPPPortability extends SPPViewForm_Portability
{
}
