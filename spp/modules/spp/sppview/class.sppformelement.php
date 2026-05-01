<?php

namespace SPPMod\SPPView;;
use SPPMod\SPPView\ViewValidator;
//require_once 'class.spphtmlelement.php';

/**
 * class SPPViewForm_Element
 * Represents an element of form.
 *
 * @author Satya Prakash Shukla
 */
class SPPViewForm_Element extends \SPPMod\SPPView\ViewTag {
    protected $validators = array();
    protected $errors = array();
    protected string $label = '';
    protected string $helpText = '';
    protected bool $isGrouped = true; 
    protected static ?ViewFormTheme $activeTheme = null;
    protected ?DataTransformer $transformer = null;

    public function setTransformer(DataTransformer $t) { $this->transformer = $t; return $this; }
    public function getTransformer(): ?DataTransformer { return $this->transformer; }

    /**
     * @param string $ename
     */
    public function __construct($ename, ViewValidator $validator = null) {
        parent::__construct('input', $ename);
        
        if (self::$activeTheme === null) {
            self::$activeTheme = ViewFormTheme::getTheme('default');
        }

        // Standard interactive events
        $this->eventattrlist = array_merge($this->eventattrlist, [
            'onselect', 'onchange', 'onblur', 'onfocus', 'oninput', 'oninvalid'
        ]);

        // Standard HTML5 and ARIA attributes
        $this->attrlist = array_merge($this->attrlist, [
            'placeholder', 'required', 'autofocus', 'pattern', 'title', 
            'aria-label', 'aria-describedby', 'aria-invalid', 'aria-required',
            'min', 'max', 'step', 'readonly', 'disabled'
        ]);

        if ($validator) {
            $this->addValidator($validator);
        }
    }

    public static function setTheme(string $themeName) {
        self::$activeTheme = ViewFormTheme::getTheme($themeName);
    }

    public function setLabel(string $label) { $this->label = $label; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setHelpText(string $text) { $this->helpText = $text; return $this; }
    public function setGrouped(bool $grouped) { $this->isGrouped = $grouped; return $this; }

    public function addValidator(ViewValidator $validator) {
        $this->validators[] = $validator;
        if ($validator->is_required()) {
            $this->setAttribute('required', 'required');
            $this->setAttribute('aria-required', 'true');
        }
    }

    /**
     * Overrides getHTML to automatically wrap in a form group if enabled.
     */
    public function getHTML(): string {
        if ($this->isGrouped) {
            return self::$activeTheme->renderGroup($this);
        }
        return parent::getHTML();
    }

    /**
     * Renders the raw element without any theme wrapping.
     */
    public function renderRaw(): string {
        $this->isGrouped = false;
        $html = parent::getHTML();
        $this->isGrouped = true;
        return $html;
    }

    /**
     * Override setAttribute to handle common logic and transformation
     */
    public function setAttribute($name, $val) {
        if ($name === 'value' && $this->transformer) {
            $val = $this->transformer->transform($val);
        }
        
        if (!in_array($name, $this->attrlist)) {
            $this->attrlist[] = $name; 
        }
        return parent::setAttribute($name, $val);
    }

    public function getAttribute($name) {
        $val = parent::getAttribute($name);
        if ($name === 'value' && $this->transformer) {
            return $this->transformer->reverseTransform($val);
        }
        return $val;
    }
}
