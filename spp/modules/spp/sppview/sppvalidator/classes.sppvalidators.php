<?php

namespace SPPMod\SPPView;

/**
 * Concrete SPPView validators.
 *
 * Each validator must implement:
 *   - validate(mixed $value): bool  (Single Validators)
 *   - validateAll(): bool           (Multiple Validators)
 */

/**
 * Required field validator.
 */
class SPP_Validator_RequiredValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'A required field is left blank!')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateRequired');
        $this->applicabletags = ['input', 'select', 'textarea'];
    }
    public function is_required(): bool
    {
        return true;
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Numeric field validator.
 */
class SPP_Validator_NumericValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'The field should be numeric!')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateNumeric');
        $this->applicabletags = ['input'];
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }
        if (!is_numeric($value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Numeric field validator.
 */
class SPP_Validator_MinLengthValidator extends SPP_Single_validator
{
    public $minlength = 0;
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $min = 0, $errorholder = 'nameerror', $msg = 'Field too short!')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateMinLength');
        $this->applicabletags = ['input'];
        $this->minlength = $min;
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }
        if (strlen($value) < $this->minlength) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Numeric field validator.
 */
class SPP_Validator_MaxLengthValidator extends SPP_Single_validator
{
    public $maxlength = 0;
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $max = 0, $errorholder = 'nameerror', $msg = 'Field too long!')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateMaxLength');
        $this->applicabletags = ['input'];
        $this->maxlength = $max;
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }
        if (strlen($value) > $this->maxlength) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Multiple-element validator: at least one of the fields in the set must be filled.
 */
class SPP_Validator_OneRequiredValidator extends SPP_Multiple_Validator
{
    public function __construct(array $elems, $errorholder = 'nameerror', $msg = 'At least one of these fields must be filled')
    {
        parent::__construct($elems, $errorholder, $msg, 'validateOneRequired');
        $this->applicabletags = ['input'];
    }

    public function validateAll(?array $data = null, array $rules = []): \SPPMod\SPPView\ValidationResult
    {
        $data = $data ?? $_POST;
        $this->lastResult = new \SPPMod\SPPView\ValidationResult();
        $flag = false;

        foreach ($this->elements as $elem) {
            $id = $elem->getAttribute('id');
            if (isset($data[$id]) && trim($data[$id]) !== '') {
                $flag = true;
                break;
            }
        }

        if ($flag) {
            return $this->lastResult;
        }

        if (!$this->silent) {
            foreach ($this->elements as $elem) {
                ViewPage::addClass($elem->getAttribute('id'), 'errorclass');
                $this->lastResult->addError($elem->getAttribute('id'), $this->msg);
            }
            \SPP\SPPError::triggerUserError($this->msg);
        } else {
            foreach ($this->elements as $elem) {
                $this->lastResult->addError($elem->getAttribute('id'), $this->msg);
            }
        }

        return $this->lastResult;
    }

    public function validate(mixed $value): bool
    {
        return true;
    }
}

/**
 * Regex pattern validator.
 */
class SPP_Validator_RegexValidator extends SPP_Single_validator
{
    public $pattern = '';
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $pattern = '', $errorholder = 'nameerror', $msg = 'Invalid format!')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateRegex');
        $this->pattern = $pattern;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->pattern]);
        return parent::getJsFunction();
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match($this->pattern, (string) $value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Email validator.
 */
class SPP_Validator_EmailValidator extends SPP_Validator_RegexValidator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Please enter a valid email address.')
    {
        $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/';
        parent::__construct($elem, $pattern, $errorholder, $msg);
        $this->jsfunc = 'validateEmail';
    }

    public function getJsFunction(): string
    {
        return parent::getJsFunction();
    }
}

/**
 * Callback validator for custom logic via closures.
 */
class SPP_Validator_CallbackValidator extends SPP_Single_validator
{
    protected $closure;

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, ?callable $closure = null, $errorholder = 'nameerror', $msg = 'Custom validation failed.')
    {
        parent::__construct($elem, $errorholder, $msg, 'undefined');
        $this->closure = $closure;
    }

    public function getJsFunction(): string
    {
        return ""; // Callbacks are usually server-side only unless paired with a JS hook
    }

    public function validate(mixed $value): bool
    {
        $res = ($this->closure)($value, $this->element);
        if (!$res) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Unique validator: checks if value already exists in a database table.
 */
class SPP_Validator_UniqueValidator extends SPP_Single_validator
{
    public string $table = '';
    public string $column = '';
    public mixed $excludeId = null;

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, string $table = '', string $column = '', $errorholder = 'nameerror', $msg = 'This value is already taken.')
    {
        parent::__construct($elem, $errorholder, $msg, 'undefined');
        $this->table = $table;
        $this->column = $column;
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable($this->table);
        $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$this->column} = :val";
        $params = [':val' => $value];

        if ($this->excludeId !== null) {
            $query .= " AND id != :id";
            $params[':id'] = $this->excludeId;
        }

        $res = $db->execute_query($query, $params);
        if (($res[0]['count'] ?? 0) > 0) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Match validator: ensures value matches another field.
 */
class SPP_Validator_MatchValidator extends SPP_Single_validator
{
    public string $targetField = '';

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, string $target = '', $errorholder = 'nameerror', $msg = 'Fields do not match.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateMatch');
        $this->targetField = $target;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->targetField]);
        return parent::getJsFunction();
    }

    public function getClientScript(): ?string
    {
        $id = $this->element ? $this->element->getAttribute('id') : '';
        if (!$id || !$this->targetField)
            return null;

        return <<<JS
        const source = document.getElementById('{$id}');
        const target = document.querySelector('[name="{$this->targetField}"]') || document.getElementById('{$this->targetField}');
        if (source && target && source.value !== target.value) {
            source.setCustomValidity('{$this->msg}');
            source.reportValidity();
            return false;
        } else if (source) {
            source.setCustomValidity('');
        }
JS;
    }

    public function validate(mixed $value): bool
    {
        // Server-side match requires access to the data array, which validateAll provides.
        // For single validate calls, we might need to be passed the context.
        return true;
    }
}

/**
 * Range validator.
 */
class SPP_Validator_RangeValidator extends SPP_Single_validator
{
    public float $min = 0;
    public float $max = 0;

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $min = 0, $max = 100, $errorholder = 'nameerror', $msg = 'Value out of range.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateRange');
        $this->min = $min;
        $this->max = $max;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->min, $this->max]);
        return parent::getJsFunction();
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_numeric($value) || $value < $this->min || $value > $this->max) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * URL validator.
 */
class SPP_Validator_UrlValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid URL.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateUrl');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * JSON validator.
 */
class SPP_Validator_JsonValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid JSON format.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateJson');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        json_decode($value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * File size validator.
 */
class SPP_Validator_FileSizeValidator extends SPP_Single_validator
{
    public int $maxSize = 0; // in bytes

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, int $max = 2097152, $errorholder = 'nameerror', $msg = 'File size too large.')
    {
        parent::__construct($elem, $errorholder, $msg, 'undefined');
        $this->maxSize = $max;
    }

    public function validate(mixed $value): bool
    {
        if (is_array($value) && isset($value['size'])) {
            if ($value['size'] > $this->maxSize) {
                if (!$this->silent) {
                    ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                    \SPP\SPPError::triggerUserError($this->msg);
                }
                return false;
            }
        }
        return true;
    }
}

/**
 * File extension validator.
 */
class SPP_Validator_FileExtensionValidator extends SPP_Single_validator
{
    public array $allowed = [];

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, array $allowed = [], $errorholder = 'nameerror', $msg = 'Invalid file type.')
    {
        parent::__construct($elem, $errorholder, $msg, 'undefined');
        $this->allowed = array_map('strtolower', $allowed);
    }

    public function validate(mixed $value): bool
    {
        if (is_array($value) && isset($value['name'])) {
            $ext = strtolower(pathinfo($value['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowed)) {
                if (!$this->silent) {
                    ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                    \SPP\SPPError::triggerUserError($this->msg);
                }
                return false;
            }
        }
        return true;
    }
}

/**
 * InArray validator.
 */
class SPP_Validator_InArrayValidator extends SPP_Single_validator
{
    public array $options = [];

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, array $options = [], $errorholder = 'nameerror', $msg = 'Invalid option selected.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateInArray');
        $this->options = $options;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([json_encode($this->options)]);
        return parent::getJsFunction();
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!in_array($value, $this->options)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * CreditCard validator (Luhn check).
 */
class SPP_Validator_CreditCardValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid credit card number.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateCreditCard');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $number = preg_replace('/\D/', '', $value);
        $sum = 0;
        for ($i = 0; $i < strlen($number); $i++) {
            $digit = (int) $number[strlen($number) - $i - 1];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }
        if ($sum % 10 !== 0) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * India Specific Validators
 */

/**
 * Aadhaar validator (Verhoeff checksum).
 */
class SPP_Validator_AadhaarValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid Aadhaar number.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateAadhaar');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $number = preg_replace('/\D/', '', $value);
        if (strlen($number) !== 12) {
            return false;
        }

        $d = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
            [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
            [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
            [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
            [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
            [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
            [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
            [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
            [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
        ];
        $p = [
            [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
            [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
            [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
            [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
            [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
            [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
            [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]
        ];

        $c = 0;
        $invertedArray = array_reverse(str_split($number));
        foreach ($invertedArray as $i => $v) {
            $c = $d[$c][$p[$i % 8][$v]];
        }

        if ($c !== 0) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * PAN validator.
 */
class SPP_Validator_PanValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid PAN number.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validatePan');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', (string) $value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * GSTIN validator.
 */
class SPP_Validator_GstinValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid GSTIN.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateGstin');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', (string) $value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * IFSC validator.
 */
class SPP_Validator_IfscValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid IFSC code.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateIfsc');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/i', (string) $value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * Pincode validator.
 */
class SPP_Validator_PincodeValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid Pincode.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validatePincode');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[1-9][0-9]{5}$/', (string) $value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * India Mobile validator.
 */
class SPP_Validator_IndiaMobileValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid Mobile number.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateIndiaMobile');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^[6-9]\d{9}$/', (string) $value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * DateAfter validator: ensures date is after another field.
 */
class SPP_Validator_DateAfterValidator extends SPP_Single_validator
{
    public string $targetField = '';

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, string $target = '', $errorholder = 'nameerror', $msg = 'Date must be after the start date.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateDateAfter');
        $this->targetField = $target;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->targetField]);
        return parent::getJsFunction();
    }

    public function validate(mixed $value): bool
    {
        return true; // Requires context from validateAll
    }
}

/**
 * Password strength validator.
 */
class SPP_Validator_PasswordStrengthValidator extends SPP_Single_validator
{
    public int $minScore = 3;

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, int $min = 3, $errorholder = 'nameerror', $msg = 'Password is too weak.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validatePasswordStrength');
        $this->minScore = $min;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->minScore]);
        return parent::getJsFunction();
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $score = 0;
        if (strlen($value) >= 8) {
            $score++;
        }
        if (preg_match('/[A-Z]/', $value)) {
            $score++;
        }
        if (preg_match('/[a-z]/', $value)) {
            $score++;
        }
        if (preg_match('/[0-9]/', $value)) {
            $score++;
        }
        if (preg_match('/[^A-Za-z0-9]/', $value)) {
            $score++;
        }

        if ($score < $this->minScore) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * IBAN validator.
 */
class SPP_Validator_IbanValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid IBAN.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateIban');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $iban = strtoupper(preg_replace('/[^A-Z0-9]/', '', $value));
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        if (bcmod($numeric, '97') != 1) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * RequiredIf validator: field is required if another field has a specific value.
 */
class SPP_Validator_RequiredIfValidator extends SPP_Single_validator
{
    public string $targetField = '';
    public mixed $targetValue = '';

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, string $target = '', $val = '', $errorholder = 'nameerror', $msg = 'This field is required.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateRequiredIf');
        $this->targetField = $target;
        $this->targetValue = $val;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->targetField, $this->targetValue]);
        return parent::getJsFunction();
    }

    public function getClientScript(): ?string
    {
        $id = $this->element ? $this->element->getAttribute('id') : '';
        if (!$id || !$this->targetField)
            return null;

        return <<<JS
        const source = document.getElementById('{$id}');
        const target = document.querySelector('[name="{$this->targetField}"]') || document.getElementById('{$this->targetField}');
        if (source && target && target.value === '{$this->targetValue}') {
            if (!source.value || source.value.trim() === '') {
                source.setCustomValidity('{$this->msg}');
                source.reportValidity();
                return false;
            } else {
                source.setCustomValidity('');
            }
        } else if (source) {
            source.setCustomValidity('');
        }
JS;
    }

    public function validate(mixed $value): bool
    {
        return true; // Requires context from validateAll
    }
}

/**
 * GreaterThan validator: numeric comparison against another field.
 */
class SPP_Validator_GreaterThanValidator extends SPP_Single_validator
{
    public string $targetField = '';

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, string $target = '', $errorholder = 'nameerror', $msg = 'Value must be greater.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateGreaterThan');
        $this->targetField = $target;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->targetField]);
        return parent::getJsFunction();
    }

    public function validate(mixed $value): bool
    {
        return true;
    }
}

/**
 * IP address validator.
 */
class SPP_Validator_IpValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid IP address.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateIp');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * MAC Address validator.
 */
class SPP_Validator_MacAddressValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid MAC address.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateMacAddress');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', (string) $value)) {
            if (!$this->silent) {
                ViewPage::addClass($this->element->getAttribute('id'), 'errorclass');
                \SPP\SPPError::triggerUserError($this->msg);
            }
            return false;
        }
        return true;
    }
}

/**
 * ISBN validator.
 */
class SPP_Validator_IsbnValidator extends SPP_Single_validator
{
    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, $errorholder = 'nameerror', $msg = 'Invalid ISBN.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateIsbn');
    }

    public function validate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $isbn = preg_replace('/[- ]/', '', (string) $value);
        if (strlen($isbn) === 10) {
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += (10 - $i) * (int) $isbn[$i];
            }
            $last = strtoupper($isbn[9]) === 'X' ? 10 : (int) $isbn[9];
            if (($sum + $last) % 11 !== 0) {
                return false;
            }
        } elseif (strlen($isbn) === 13) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += ($i % 2 === 0 ? 1 : 3) * (int) $isbn[$i];
            }
            if ((10 - ($sum % 10)) % 10 !== (int) $isbn[12]) {
                return false;
            }
        } else {
            return false;
        }
        return true;
    }
}

/**
 * Remote validator: validates value against an API endpoint.
 */
class SPP_Validator_RemoteValidator extends SPP_Single_validator
{
    public string $url = '';

    public function __construct(?\SPPMod\SPPView\ViewTag $elem = null, string $url = '', $errorholder = 'nameerror', $msg = 'Validation failed.')
    {
        parent::__construct($elem, $errorholder, $msg, 'validateRemote');
        $this->url = $url;
    }

    public function getJsFunction(): string
    {
        $this->setJsParams([$this->url]);
        return parent::getJsFunction();
    }

    public function validate(mixed $value): bool
    {
        // Server-side remote validation logic (e.g., cURL call or internal service)
        return true;
    }
}

// Class Aliases for common validators used in scaffolding
class SPPRequiredValidator extends SPP_Validator_RequiredValidator
{
}
class SPPNumericValidator extends SPP_Validator_NumericValidator
{
}
class SPPOneRequiredValidator extends SPP_Validator_OneRequiredValidator
{
}
class SPPRegexValidator extends SPP_Validator_RegexValidator
{
}
class SPPEmailValidator extends SPP_Validator_EmailValidator
{
}
class SPPCallbackValidator extends SPP_Validator_CallbackValidator
{
}
