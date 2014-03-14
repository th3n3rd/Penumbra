<?php

namespace Storm\Pinq;

use \Storm\Core\Object;
use \Storm\Core\Object\Expressions as O;

class DataRequest extends Request implements Object\IDataRequest {
    private $AliasExpressionMap;
    
    public function __construct(
            Object\IEntityMap $EntityMap, 
            IFunctionToExpressionTreeConverter $FunctionToExpressionTreeConverter, 
            $DataFunctionOrExpression,
            array $GroupByFunctions = [], 
            array $AggregatePredicateFunctions = [], 
            \Storm\Core\Object\ICriteria $Criteria = null) {
        
        $ReturnDataExpression = is_callable($DataFunctionOrExpression) ? 
                $this->ParseFunctionReturn($DataFunctionOrExpression, 'data', [0 => O\Expression::Entity()])
                : $DataFunctionOrExpression;
        
        if(!($DataFunctionOrExpression instanceof O\Expression)) {
            throw new PinqException('Supplied data function must be callable or expression');
        }
        
        $this->AliasExpressionMap = $this->ParseDataExpression($ReturnDataExpression);
        
        parent::__construct(
                $EntityMap, 
                $FunctionToExpressionTreeConverter, 
                $GroupByFunctions, 
                $AggregatePredicateFunctions, 
                $Criteria);
    }

    final public function GetAliasExpressionMap() {
        return $this->AliasExpressionMap;
    }
    
    private function ParseDataExpression(O\Expression $ReturnDataExpression) {
        if(!($ReturnDataExpression instanceof O\ArrayExpression)) {
            throw new PinqException(
                    'Return value for data request must be an array expression: %s given',
                    $ReturnDataExpression->GetType());
        }
        
        $AliasExpressionMap = [];
        
        $KeyExpressions = $ReturnDataExpression->GetKeyExpressions();
        $ValueExpressions = $ReturnDataExpression->GetValueExpressions();
        
        foreach ($KeyExpressions as $Key => $KeyExpression) {
            if(!($KeyExpression instanceof O\ValueExpression)) {
                throw new PinqException(
                        'Return array for data request must contain constant keys.');
            }
            
            $ValueExpression = $ValueExpressions[$Key];
            
            if($KeyExpression !== null) {
                $Alias = $KeyExpression->GetValue();
                $AliasExpressionMap[$Alias] = $ValueExpression;
            }
            else {
                $AliasExpressionMap[] = $ValueExpression;
            }
        }
        
        if(count($AliasExpressionMap) === 0) {
            if(!($KeyExpression instanceof O\ValueExpression)) {
                throw new PinqException(
                        'Return array must contain atleast one key value pair');
            }
        }
        
        return $AliasExpressionMap;
    }

}

?>