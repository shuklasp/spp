<?php
namespace SPPMod\Parikshak\Attributes;

use Attribute;

/**
 * #[DataProvider] Attribute
 * Used to define a method that provides data to a test method.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class DataProvider
{
    private string $methodName;

    public function __construct(string $methodName)
    {
        $this->methodName = $methodName;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }
}
