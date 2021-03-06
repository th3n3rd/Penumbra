<?php

namespace Penumbra\Core\Object\Expressions;

/**
 * Expression representing a resolved value.
 * 
 * @author Elliot Levin <elliot@aanet.com.au>
 */
class ValueExpression extends Expression {
    private $Value;
    public function __construct($Value) {
        $this->Value = $Value;
    }
    
    /**
     * @return mixed The resolved value
     */
    public function GetValue() {
        return $this->Value;
    }
    
    public function Traverse(ExpressionWalker $Walker) {
        return $Walker->WalkValue($this);
    }
    
    public function Simplify() {
        return $this;
    }
    
    /**
     * @return self
     */
    public function Update($Value) {
        if($this->Value === $Value) {
            return $this;
        }
        
        return new self($Value);
    }
}

?>