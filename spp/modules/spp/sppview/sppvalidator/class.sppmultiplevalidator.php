<?php

namespace SPPMod\SPPView;

/**
 * class SPP_Single_validator
 *
 * @author Satya Prakash Shukla
 */
// require_once 'class.sppvalidator.php';

abstract class SPP_Multiple_Validator extends ViewValidator
{
    protected $elements = [];

    public function __construct(array $elems, $errorholder = 'nameerror', $msg = 'Validation error', $jsfunc = 'undefined')
    {
        parent::__construct(null, $errorholder, $msg, $jsfunc);
        $this->elements = $elems;

        foreach ($this->elements as $elem) {
            $id = $elem->getAttribute('id');
            if ($id) {
                $this->attachedto[$id] = [
                    'element' => $elem,
                    'event' => 'manual',
                    'msg' => $msg
                ];
            }
        }
    }

    public function getJsFunction(): string
    {
        $jsarr = '[';
        foreach ($this->elements as $elem) {
            if (strlen($jsarr) > 1) {
                $jsarr .= ',';
            }
            $jsarr .= '\''.$elem->getAttribute('id').'\'';
        }
        $jsarr .= ']';
        $fn = $this->jsfunc.'(\''.$this->errorholder.'\',\''.$this->msg.'\','.$jsarr.')';
        return $fn;
    }
}
