<?php

namespace SPPMod\SPPView;

class ViewFormDispatcher
{
    private static $formslist = [];
    private static $elementslist = [];

    public static function getFormsList()
    {
        return self::$formslist;
    }

    public static function addForm(ViewForm $form)
    {
        foreach (self::$formslist as $fl) {
            if ($fl == $form) {
                return false;
            }
        }
        self::$formslist[$form->getAttribute('id')] = $form;
        return true;
    }

    public static function processForms()
    {
        if (array_key_exists('__spp_form', $_POST)) {
            $formId = $_POST['__spp_form'];
            if (!array_key_exists($formId, self::$formslist)) {
                return;
            }
            $callfunc = $formId . '_submitted';
            self::$formslist[$formId]->doValidation();
            if (function_exists($callfunc)) {
                $callfunc();
            }
        }
    }

    public static function addElement(\SPPMod\SPPView\ViewTag $ename)
    {
        foreach (self::$elementslist as $fl) {
            if ($fl == $ename) {
                return false;
            }
        }
        self::$elementslist[$ename->getAttribute('id')] = $ename;
        return true;
    }

    public static function getElementsList()
    {
        return self::$elementslist;
    }

    public static function getElement($name)
    {
        return self::$elementslist[$name] ?? null;
    }
}
