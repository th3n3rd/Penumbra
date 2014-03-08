<?php

namespace Storm\Core\Object\Expressions;

use \Storm\Core\Object;

/**
 * @author Elliot Levin <elliot@aanet.com.au>
 */
class ExpressionWalker {
    
    final public static function On($Expression) {
        return $Expression === null ? null : $Expression->Traverse($this);
    }
    
    final public function Walk(Expression $Expression = null) {
        return $Expression === null ? null : $Expression->Traverse($this);
    }
    
    final public function WalkAll(array $Expressions) {
        $VisitedExpressions = [];
        foreach ($Expressions as $Key => $Expression) {
            $VisitedExpressions[$Key] = $Expression->Traverse($Expression);
        }
        
        return $VisitedExpressions;
    }
    
    public function WalkArray(ArrayExpression $Expression) {
        return $Expression->Update(
                $this->WalkAll($Expression->GetKeyExpressions()), 
                $this->WalkAll($Expression->GetValueExpressions()));
    }
    
    public function WalkAssignment(AssignmentExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetAssignmentValueExpression()), 
                $Expression->GetOperator(),
                $this->Walk($Expression->GetAssignToExpression()));
    }
    
    public function WalkBinaryOperation(BinaryOperationExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetLeftOperandExpression()), 
                $Expression->GetOperator(),
                $this->Walk($Expression->GetRightOperandExpression()));
    }
    
    public function WalkUnaryOperation(UnaryOperationExpression $Expression) {
        return $Expression->Update(
                $Expression->GetOperator(),
                $this->Walk($Expression->GetOperandExpression()));
    }
    
    public function WalkCast(CastExpression $Expression) {
        return $Expression->Update(
                $Expression->GetCastType(),
                $this->Walk($Expression->GetCastValueExpression()));
    }
    
    public function WalkField(FieldExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetValueExpression()),
                $this->Walk($Expression->GetName()));
    }
    
    public function WalkMethodCall(MethodCallExpression $Expression) {
        return $Expression->Update(
                $this->WalkAll($Expression->GetArgumentExpressions()),
                $this->Walk($Expression->GetName()));
    }
    
    public function WalkIndex(IndexExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetValueExpression()),
                $this->Walk($Expression->GetIndex()));
    }
    
    public function WalkInvocation(InvocationExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetValueExpression()),
                $this->WalkAll($Expression->GetArgumentExpressions()));
    }
    
    public function WalkFunctionCall(FunctionCallExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetName()),
                $this->WalkAll($Expression->GetArgumentExpressions()));
    }
    
    public function WalkEntity(EntityExpression $Expression) {
        return $Expression;
    }
    
    public function WalkNew(NewExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetClassType()),
                $this->WalkAll($Expression->GetArgumentExpressions()));
    }
    
    public function WalkProperty(PropertyExpression $Expression) {
        return $Expression->Update(
                $Expression->GetProperty(),
                $this->Walk($Expression->GetParentPropertyExpression()));
    }
    
    public function WalkReturn(ReturnExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetValueExpression()));
    }
    
    public function WalkTernary(TernaryExpression $Expression) {
        return $Expression->Update(
                $this->Walk($Expression->GetConditionExpression()),
                $this->Walk($Expression->GetIfTrueExpression()),
                $this->Walk($Expression->GetIfFalseExpression()));
    }
    
    public function WalkUnresolvedValue(UnresolvedVariableExpression $Expression) {
        return $Expression;
    }
    
    public function WalkValue(ValueExpression $Expression) {
        return $Expression;
    }
}

?>