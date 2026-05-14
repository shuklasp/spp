<?php

namespace SPPMod\SPPView;

/**
 * abstract class ViewValidator
 * Base class for all SPPView field validators.
 *
 * Concrete subclasses must implement:
 *   - getJsFunction(): returns the client-side JS function call string
 *   - validate(mixed $value): bool — server-side validation logic
 *
 * @author Satya Prakash Shukla
 */
class ViewValidator extends \SPP\SPPObject
{
    /**
     * Tracks whether this subclass has already registered its CSS/JS assets.
     * Declared per-subclass via late static binding (static::$included).
     */
    protected static bool $included = false;

    /** @var mixed Callback (reserved for future use or subclass event hooks) */
    protected mixed $callback;

    /** @var string JS validation function name */
    protected string $jsfunc = '';

    /**
     * Element types that this validator may be attached to.
     * Empty array means no restriction.
     * @var array<string>
     */
    protected array $applicabletags = [];

    /** @var string ID of the DOM element that shows the error message */
    protected string $errorholder = '';

    /** @var string Default error message */
    protected string $msg = '';

    /**
     * Map of attached elements: id => ['element' => ViewTag, 'event' => string, 'msg' => string]
     * Stores per-element messages independently of the global $msg.
     * @var array<string, array{element: \SPPMod\SPPView\ViewTag, event: string, msg: string}>
     */
    protected array $attachedto = [];

    /** @var bool If true, suppresses side-effects like triggerUserError and ViewPage::addClass */
    protected bool $silent = false;

    /** @var ValidationResult|null Holds results of the last validation run */
    protected ?ValidationResult $lastResult = null;

    public function __construct(mixed $callback = null, string $errorholder = '', string $msg = '', string $jsfunc = '')
    {
        $this->callback = $callback;
        $this->errorholder = $errorholder;
        $this->msg = $msg;
        $this->jsfunc = $jsfunc;

        // Use late static binding so each concrete subclass tracks its own flag
        if (!static::$included) {
            static::$included = true;
            ViewPage::addCssIncludeFile(SPP_CSS_URI . SPP_US . 'sppview/sppvalidations.css');
            ViewPage::addJsIncludeFile(SPP_JS_URI . SPP_US . 'sppview/sppvalidations.js');
        }
    }

    /**
     * Changes the error holder element and re-applies event handlers on all
     * already-attached elements so they reference the new holder.
     */
    public function setErrorHolder(string $hld): void
    {
        $this->errorholder = $hld;
        foreach ($this->attachedto as $entry) {
            $entry['element']->setAttribute($entry['event'], $this->getJsFunction());
        }
    }

    /**
     * Changes the default error message.
     * Does NOT override per-element messages set via attachTo().
     */
    public function setMessage(string $msg): void
    {
        $this->msg = $msg;
    }

    /**
     * Enables or disables silent mode (suppresses UI side-effects).
     */
    public function setSilent(bool $silent): void
    {
        $this->silent = $silent;
    }

    /**
     * Returns the result of the last validation run.
     */
    public function getLastResult(): ?ValidationResult
    {
        return $this->lastResult;
    }

    /**
     * Returns the JS function call string for client-side validation.
     * Implementations must incorporate $this->errorholder and $this->msg.
     */
    public function getJsFunction(): string
    {
        return '';
    }

    /**
     * Server-side validation of a submitted value.
     *
     * @param mixed $value The value to validate (e.g. from $_POST)
     * @return bool         True if valid, false otherwise
     */
    public function validate(mixed $value): bool
    {
        return true;
    }

    public function is_required(): bool
    {
        return false;
    }

    /**
     * Attaches this validator to a ViewTag element on a given DOM event.
     *
     * Validates that the element type is in $applicabletags (if restrictions exist).
     * Stores a per-element message independently of the global $msg property.
     *
     * @param \SPPMod\SPPView\ViewTag $elem  The element to attach to
     * @param string                  $event DOM event name (e.g. 'onblur')
     * @param string                  $msg   Optional override message for this element only
     * @throws \SPP\SPPException if the element type is not in applicabletags
     */
    public function attachTo(\SPPMod\SPPView\ViewTag $elem, string $event, string $msg = ''): void
    {
        // Enforce applicable tag restrictions
        if (!empty($this->applicabletags)) {
            $tagName = strtolower($elem->getTagName());
            if (!in_array($tagName, $this->applicabletags, true)) {
                throw new \SPP\SPPException(
                    'Validator ' . static::class . ' cannot be attached to <' . $tagName . '>. '
                    . 'Applicable tags: ' . implode(', ', $this->applicabletags)
                );
            }
        }

        // Store per-element message; fall back to global msg if not provided
        $elementMsg = ($msg !== '') ? $msg : $this->msg;

        $id = $elem->getAttribute('id');
        $this->attachedto[$id] = [
            'element' => $elem,
            'event' => $event,
            'msg' => $elementMsg,
        ];

        $elem->setAttribute($event, $this->getJsFunction());
    }

    /**
     * Entry point for server-side validation.
     * 
     * @param array|null $data Optional data array (defaults to $_POST)
     * @param array $rules Optional rules array [field => [rule1, rule2]]
     * @return \SPPMod\SPPView\ValidationResult
     */
    public function validateAll(?array $data = null, array $rules = []): \SPPMod\SPPView\ValidationResult
    {
        $data = $data ?? $_POST;
        $this->lastResult = new \SPPMod\SPPView\ValidationResult();
        
        if (!empty($rules)) {
            foreach ($rules as $field => $fieldRules) {
                $value = $data[$field] ?? null;
                foreach ($fieldRules as $rule) {
                    $v = $this->resolveValidator($rule);
                    if ($v) {
                        $v->setSilent($this->silent);
                        if (!$v->validate($value)) {
                            $this->lastResult->addError($field, $v->getMsg() ?: "Validation failed for $field");
                        }
                    }
                }
            }
            return $this->lastResult;
        }

        foreach ($this->getAttachedIds() as $id) {
            $value = $data[$id] ?? null;
            if (!$this->validate($value)) {
                $this->lastResult->addError($id, $this->msg);
            }
        }
        return $this->lastResult;
    }

    /**
     * Resolves a rule string (e.g. 'required') to a validator instance.
     */
    private function resolveValidator($rule): ?ViewValidator
    {
        $params = [];
        $name = '';
        
        if (is_array($rule)) {
            $name = strtolower($rule['type'] ?? '');
            $params = $rule;
        } else {
            $parts = explode(':', $rule);
            $name = strtolower($parts[0]);
            $paramStr = $parts[1] ?? null;
            if ($paramStr) $params['value'] = $paramStr;
        }

        $map = [
            'required' => '\SPPMod\SPPView\SPP_Validator_RequiredValidator',
            'email'    => '\SPPMod\SPPView\SPP_Validator_EmailValidator',
            'regex'    => '\SPPMod\SPPView\SPP_Validator_RegexValidator',
            'min'      => '\SPPMod\SPPView\SPP_Validator_MinLengthValidator',
            'max'      => '\SPPMod\SPPView\SPP_Validator_MaxLengthValidator',
            'unique'   => '\SPPMod\SPPView\SPP_Validator_UniqueValidator',
            'match'    => '\SPPMod\SPPView\SPP_Validator_MatchValidator',
            'range'    => '\SPPMod\SPPView\SPP_Validator_RangeValidator',
            'url'      => '\SPPMod\SPPView\SPP_Validator_UrlValidator',
            'json'     => '\SPPMod\SPPView\SPP_Validator_JsonValidator',
            'creditcard' => '\SPPMod\SPPView\SPP_Validator_CreditCardValidator',
            'filesize' => '\SPPMod\SPPView\SPP_Validator_FileSizeValidator',
            'extension' => '\SPPMod\SPPView\SPP_Validator_FileExtensionValidator',
            'in'       => '\SPPMod\SPPView\SPP_Validator_InArrayValidator',
            'aadhaar'  => '\SPPMod\SPPView\SPP_Validator_AadhaarValidator',
            'pan'      => '\SPPMod\SPPView\SPP_Validator_PanValidator',
            'gstin'    => '\SPPMod\SPPView\SPP_Validator_GstinValidator',
            'ifsc'     => '\SPPMod\SPPView\SPP_Validator_IfscValidator',
            'pincode'  => '\SPPMod\SPPView\SPP_Validator_PincodeValidator',
            'indmobile' => '\SPPMod\SPPView\SPP_Validator_IndiaMobileValidator',
            'dateafter' => '\SPPMod\SPPView\SPP_Validator_DateAfterValidator',
            'passwordstrength' => '\SPPMod\SPPView\SPP_Validator_PasswordStrengthValidator',
            'iban'     => '\SPPMod\SPPView\SPP_Validator_IbanValidator',
            'requiredif' => '\SPPMod\SPPView\SPP_Validator_RequiredIfValidator',
            'gt'       => '\SPPMod\SPPView\SPP_Validator_GreaterThanValidator',
            'ip'       => '\SPPMod\SPPView\SPP_Validator_IpValidator',
            'mac'      => '\SPPMod\SPPView\SPP_Validator_MacAddressValidator',
            'isbn'     => '\SPPMod\SPPView\SPP_Validator_IsbnValidator',
            'remote'   => '\SPPMod\SPPView\SPP_Validator_RemoteValidator',
        ];

        if (isset($map[$name]) && class_exists($map[$name])) {
            $class = $map[$name];
            
            // Handle complex constructors if needed, or set properties
            $instance = new $class();
            
            if (!empty($params)) {
                foreach ($params as $k => $v) {
                    if ($k === 'message') { $instance->msg = $v; continue; }
                    if (property_exists($instance, $k)) $instance->$k = $v;
                }
                
                // Positional / rule-string compatibility
                $p = $params['value'] ?? null;
                if ($p !== null) {
                    if ($name === 'min') $instance->minlength = (int)$p;
                    if ($name === 'max') $instance->maxlength = (int)$p;
                    if ($name === 'regex') $instance->pattern = $p;
                    if ($name === 'range') {
                        $range = explode('-', $p);
                        $instance->min = (float)($range[0] ?? 0);
                        $instance->max = (float)($range[1] ?? 100);
                    }
                    if ($name === 'in') {
                        $instance->options = explode(',', $p);
                    }
                    if ($name === 'requiredif' || $name === 'gt') {
                        $instance->targetField = $p;
                    }
                }
            }
            return $instance;
        }
        return null;
    }

    /**
     * Fallback for base class.
     */
    
    /**
     * Gets the error message.
     */
    public function getMsg(): string
    {
        return $this->msg;
    }

    /**
     * Returns an array of element IDs that this validator is attached to.
     * @return array<string>
     */
    public function getAttachedIds(): array
    {
        return array_keys($this->attachedto);
    }
}
