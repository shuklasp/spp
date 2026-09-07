<?php
namespace SPPMod\Parikshak\DSL;

class Expectation {
    private $value;
    
    public function __construct($value) {
        $this->value = $value;
    }
    
    public function toBe($expected) {
        if ($this->value !== $expected) {
            $valStr = is_scalar($this->value) ? (string)$this->value : gettype($this->value);
            $expStr = is_scalar($expected) ? (string)$expected : gettype($expected);
            throw new \Exception("Failed asserting that '$valStr' strictly equals expected '$expStr'.");
        }
        return $this;
    }
    
    public function toBeTrue() {
        return $this->toBe(true);
    }
    
    public function toBeFalse() {
        return $this->toBe(false);
    }
    
    public function toBeNull() {
        return $this->toBe(null);
    }
}
