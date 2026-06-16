<?php

namespace SPPMod\SPPView;

/**
 * abstract class SPP_Single_validator
 *
 * @author Satya Prakash Shukla
 */

abstract class SPP_Single_validator extends ViewValidator
{
    protected $element;

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Validation error', $jsfunc = 'undefined')
    {
        parent::__construct(null, $errorholder, $msg, $jsfunc);
        $this->element = $elem;

        if ($elem) {
            // Register in attachedto for validateAll support
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

    public function setElement(\SPPMod\SPPView\ViewTag $elem)
    {
        //parent::__construct();
        $this->element = $elem;
    }

    protected array $jsParams = [];

    public function setJsParams(array $params): void
    {
        $this->jsParams = $params;
    }

    public function getJsFunction(): string
    {
        $id = $this->element ? $this->element->getAttribute('id') : '';

        $params = [
            "'" . addslashes($this->errorholder) . "'",
            "'" . addslashes($this->msg) . "'",
            "'" . addslashes($id) . "'"
        ];

        foreach ($this->jsParams as $p) {
            $params[] = is_string($p) ? "'" . addslashes($p) . "'" : $p;
        }

        return $this->jsfunc . '(' . implode(', ', $params) . ')';
    }

    /**
     * Generates pure JavaScript logic for this validator when HTML5 native validation is insufficient.
     * Inheriting classes should override this if they cannot be mapped to simple HTML5 attributes.
     */
    public function getClientScript(): ?string
    {
        return null;
    }
}
