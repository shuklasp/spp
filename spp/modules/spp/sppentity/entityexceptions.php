<?php
namespace SPP\Exceptions;

class AttributeNotFoundException extends \SPP\SPPException{
    public function  __construct($message,$code=2000) {
        parent::__construct($message, $code);
    }
}

class EntityNotFoundException extends \SPP\SPPException
{
    public function  __construct($message, $code = 2000)
    {
        parent::__construct($message, $code);
    }
}

class EntityConfigurationException extends \SPP\SPPException
{
    public function  __construct($message, $code = 2001)
    {
        parent::__construct($message, $code);
    }
}

class EntityValidationException extends \SPP\SPPException
{
    private $result;

    public function __construct(\SPPMod\SPPView\ValidationResult $result)
    {
        $this->result = $result;
        $errors = implode(", ", $result->getAllErrors());
        parent::__construct("Entity validation failed: " . $errors, 422);
    }

    public function getResult(): \SPPMod\SPPView\ValidationResult
    {
        return $this->result;
    }
}

?>
