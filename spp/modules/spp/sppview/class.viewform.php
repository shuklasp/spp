<?php

namespace SPPMod\SPPView;

use SPP\Exceptions\UnknownPropertyException as UnknownPropertyException;
use SPP\Exceptions\VarNotFoundException as VarNotFoundException;
use SPP\SPPException as SPPException;
use SPPMod\SPPView\ViewTag;
use SPPMod\SPPView\ViewValidator;

/*require_once 'sppsystemexceptions.php';
require_once 'class.spphtmlelement.php';
require_once 'classes.sppvalidators.php';*/

/**
 * class Form
 * Handles a form in system.
 *
 * @author Satya Prakash Shukla
 */
class ViewForm extends ViewTag
{
    private $elements = [];
    private $globalset;
    private $validators = [];
    private static $valstatus = true;
    private $entityClass;
    private $matter;

    public function __construct($ename, $method = 'post', $act = '', $id = null)
    {
        parent::__construct('form', $ename);
        $this->isemptyflag = false;
        $this->attrlist = ['action', 'accept', 'accept-charset', 'enctype', 'method', 'name', 'target', 'data-onsuccess', 'data-onerror', 'data-onbeforesubmit'];
        $this->eventattrlist[] = 'onsubmit';
        $this->eventattrlist[] = 'onreset';

        if ($act == '') {
            $this->attributes['action'] = $_SERVER['PHP_SELF'];
        } else {
            $this->attributes['action'] = $act;
        }

        $this->attributes['name'] = $ename;
        $this->attributes['id'] = $id ?? 'spp_' . $ename;

        $method = strtolower($method);
        if (in_array($method, ['post', 'get', 'put', 'delete'])) {
            $this->attributes['method'] = $method;
        } else {
            throw new \SPP\SPPException('Invalid method ' . $method . ' declared for form ' . $this->getAttribute('name'));
        }
        $this->globalset = [];

        ViewPage::addForm($this);
    }

    public function setMethod($method)
    {
        $this->attributes['method'] = strtolower($method);
    }

    public function setAction($action)
    {
        $this->attributes['action'] = $action;
    }

    public function setTheme(string $themeName)
    {
        SPPViewForm_Element::setTheme($themeName);
    }

    public function setOnsubmit($onsubmit)
    {
        $this->attributes['onsubmit'] = $onsubmit;
    }


    public function addValidator(ViewValidator $val, $msg = '')
    {
        if ($msg != '') {
            $val->setMessage($msg);
        }
        $this->validators[] = $val;
    }

    public function attachValidator(ViewValidator $val, ViewTag $elem, $event, $errhold, $msg = '')
    {
        $val->setErrorHolder($errhold);
        $val->attachTo($elem, $event, $msg);
    }

    public function doValidation()
    {
        foreach ($this->validators as $val) {
            $valRes = $val->validateAll();
            $isValid = is_object($valRes) && method_exists($valRes, 'isValid') ? $valRes->isValid() : (bool) $valRes;
            if (self::$valstatus === true) {
                self::$valstatus = $isValid;
            }
        }
    }

    public static function isValidated()
    {
        return self::$valstatus;
    }

    public function getErrors(): array
    {
        $errors = [];
        foreach ($this->validators as $val) {
            $res = $val->getLastResult();
            if ($res && !$res->isValid()) {
                $errors = array_merge_recursive($errors, $res->getErrors());
            }
        }
        return $errors;
    }

    public function isSubmitted(): bool
    {
        return ($_POST['__spp_form'] ?? '') === $this->getAttribute('name');
    }

    public function isValid(): bool
    {
        $this->doValidation();
        return (bool) self::$valstatus;
    }

    public function save(): bool
    {
        if (($this->entityInstance || $this->entityClass) && ($this->isValid())) {
            $entity = $this->entityInstance ?: new $this->entityClass();

            foreach ($this->elements as $id => $elem) {
                $name = $elem->getAttribute('name') ?: $id;
                $val = $_POST[$name] ?? null;
                if ($val !== null) {
                    $entity->set(rtrim($name, '[]'), $val);
                }
            }
            return $entity->save();
        }
        return false;
    }


    public function fill(\SPPMod\SPPDB\SPPEntity $entity, array $data = null): self
    {
        $payload = $data ?? $_POST;
        foreach ($this->elements as $id => $elem) {
            $name = $elem->getAttribute('name') ?: $id;
            $cleanName = rtrim($name, '[]');
            if (isset($payload[$name])) {
                $entity->set($cleanName, $payload[$name]);
            }
        }
        return $this;
    }


    /**
     * Function addElement()
     * Adds an element to the form.
     *
     * @param mixed $elem
     */

    public function addElement(\SPPMod\SPPView\ViewTag $elem)
    {
        $ename = $elem->getAttribute('id');
        if (array_key_exists($ename, $_POST)) {
            $elem->setAttribute('value', $_POST[$ename]);
        }
        $this->elements[$ename] = $elem;
        $this->addChild($elem);
    }

    public function startForm()
    {
        echo $this->getStart();

        // Automated CSRF Protection
        $token = \SPP\SPPSession::getCsrfToken();
        echo '<input type="hidden" name="_csrf_token" value="' . $token . '" />';

        // The hidden field is only for legacy multi-form-per-page detection in processForms()
        echo '<input type="hidden" name="__spp_form" id="__spp_form_' . $this->getAttribute('id') . '" value="' . $this->getAttribute('name') . '" />';
    }

    public function endForm()
    {
        // Inject dynamic scripts from validators
        $scripts = [];
        if (isset($this->validators) && is_array($this->validators)) {
            foreach ($this->validators as $validator) {
                if (method_exists($validator, 'getClientScript')) {
                    $script = $validator->getClientScript();
                    if ($script) {
                        $scripts[] = $script;
                    }
                }
            }
        }

        if (!empty($scripts)) {
            $formId = $this->getAttribute('id') ?: $this->getAttribute('name');
            if ($formId) {
                echo '<script>';
                echo 'document.addEventListener("DOMContentLoaded", function() {';
                echo '  var formElem = document.getElementById("' . $formId . '");';
                echo '  if (formElem) {';
                echo '    formElem.addEventListener("submit", function(e) {';
                echo '      let hasError = false;';
                foreach ($scripts as $script) {
                    echo '      if (!hasError) { hasError = ((function() { ' . $script . ' })() === false); }';
                }
                echo '      if (hasError) {';
                echo '        e.preventDefault();';
                echo '      }';
                echo '    });';
                echo '  }';
                echo '});';
                echo '</script>';
            }
        }

        echo $this->getEnd();
    }

    /**
     * Function setFormGlobal()
     * @param string $gvar
     * @param mixed $gval
     * @return mixed
     */
    public function setFormGlobal($gvar, $gval)
    {
        return $this->globalset[$gvar] = $gval;
    }

    /**
     * Function getFormGlobal()
     * @param string $gvar
     * @return mixed
     */
    public function getFormGlobal($gvar)
    {
        if (array_key_exists($gvar, $this->globalset)) {
            return $this->globalset[$gvar];
        } else {
            throw new VarNotFoundException('Variable ' . $gvar . ' not found in form ' . $this->getAttribute('name'));
        }
    }

    /**
     * Function get
     * Gets values of various properties.
     *
     * @param mixed $propname
     * @return mixed
     */
    public function get($propname)
    {
        switch ($propname) {
            case 'name':
            case 'id':
            case 'action':
            case 'method':
            case 'onsubmit':
                return $this->attributes[$propname] ?? null;
            case 'element':
                return $this->elements;
            case 'validators':
                return $this->validators;
            default:
                throw new UnknownPropertyException('Unknown property ' . $propname . ' in form');
        }
    }

    /**
     * Function set()
     * Sets the value of a property.
     *
     * @param mixed $propname
     * @param mixed $propval
     * @return mixed
     */
    public function set($propname, $propval)
    {
        switch ($propname) {
            case 'name':
            case 'id':
            case 'action':
            case 'method':
            case 'onsubmit':
                return $this->attributes[$propname] = $propval;
            default:
                throw new UnknownPropertyException('Unknown property ' . $propname . ' in form');
        }
    }

    public function getHTML(): string
    {
        ob_start();
        $this->startForm();
        if (isset($this->children)) {
            echo $this->getChildren();
        }
        $this->endForm();
        return ob_get_clean();
    }

    private $entityInstance;

    public function setEntityClass($class)
    {
        $this->entityClass = $class;
    }
    public function getEntityClass()
    {
        return $this->entityClass;
    }
    public function setEntityInstance($entity)
    {
        $this->entityInstance = $entity;
    }
    public function getEntityInstance()
    {
        return $this->entityInstance;
    }
    public function setMatter($title)
    {
        $this->matter = $title;
    }
    public function getMatter()
    {
        return $this->matter;
    }

    /**
     * Binds an entity to the form by setting values of its elements.
     */
    public function bind(\SPPMod\SPPDB\SPPEntity $entity)
    {
        foreach ($this->elements as $id => $elem) {
            $name = $elem->getAttribute('name') ?: $id;
            $attrName = rtrim($name, '[]');

            if ($entity->attributeExists($attrName)) {
                $value = $entity->get($attrName);

                // Special handling for many-to-many role IDs if applicable
                if ($attrName === 'role_ids' && method_exists($entity, 'getRoles')) {
                    $value = $entity->getRoles();
                }

                $elem->setAttribute('value', $value);
            }
        }
    }

    public function getChild($name)
    {
        return $this->elements[$name] ?? null;
    }

    /**
     * Registers an element in the form's element map without necessarily adding it as a direct child.
     * Useful for elements wrapped in containers.
     */
    public function registerElement(\SPPMod\SPPView\ViewTag $elem)
    {
        $ename = $elem->getAttribute('id') ?: $elem->getAttribute('name');
        if ($ename) {
            $this->elements[$ename] = $elem;
        }
    }
}
