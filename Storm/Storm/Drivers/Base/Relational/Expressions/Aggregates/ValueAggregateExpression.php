<?php

namespace Storm\Core\Object\Expressions\Aggregates;

use \Storm\Core\Object\Expressions\Expression;

/**
 * Expression for an aggregate function.
 * Count, Maximum, Minimum, Average, Sum, Implode, All, Any
 * 
 * @author Elliot Levin <elliot@aanet.com.au>
 */
abstract class ValueAggregateExpression extends AggregateExpression {
    private $ValueExpression;
    
    final public function __construct(Expression $ValueExpression) {
        parent::__construct();
        $this->ValueExpression = $ValueExpression;
    }

    /**
     * @return Expression
     */
    final public function GetValueExpression() {
        return $this->ValueExpression;
    }
    
    final public function Simplify() {
        return $this->Update(
                $this->ValueExpression->Simplify());
    }
    
    final public function Update(Expression $ValueExpression) {
        if($this->ValueExpression === $ValueExpression) {
            return $this;
        }
        
        return new static($ValueExpression);
    }
}

?>