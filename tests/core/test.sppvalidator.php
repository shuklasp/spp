<?php
namespace SPP\Tests\Core;

use SPPMod\Parikshak\SPPTestCase;
use SPPMod\SPPView\SPP_Validator_RequiredValidator;
use SPPMod\SPPView\SPP_Validator_EmailValidator;
use SPPMod\SPPView\SPP_Validator_MinLengthValidator;

class SPPValidatorTest extends SPPTestCase
{
    public function testRequiredValidator()
    {
        $validator = new SPP_Validator_RequiredValidator();
        $validator->setSilent(true); // Prevent triggering UserError in CLI

        $this->assertTrue($validator->validate('hello'), 'Should pass for non-empty string');
        $this->assertFalse($validator->validate(''), 'Should fail for empty string');
        $this->assertFalse($validator->validate(null), 'Should fail for null');
        $this->assertFalse($validator->validate('   '), 'Should fail for whitespace');
    }

    public function testEmailValidator()
    {
        $validator = new SPP_Validator_EmailValidator();
        $validator->setSilent(true);

        $this->assertTrue($validator->validate('test@example.com'), 'Should pass valid email');
        $this->assertTrue($validator->validate(''), 'Should pass empty string (email is optional unless required)');
        $this->assertFalse($validator->validate('test@example'), 'Should fail invalid email');
        $this->assertFalse($validator->validate('invalid-email'), 'Should fail invalid email format');
    }

    public function testMinLengthValidator()
    {
        $validator = new SPP_Validator_MinLengthValidator(null, 5);
        $validator->setSilent(true);

        $this->assertTrue($validator->validate('12345'), 'Should pass length 5');
        $this->assertTrue($validator->validate('123456'), 'Should pass length > 5');
        $this->assertTrue($validator->validate(''), 'Should pass empty string (length is optional unless required)');
        $this->assertFalse($validator->validate('1234'), 'Should fail length < 5');
    }
}
