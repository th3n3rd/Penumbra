<?php

namespace Penumbra\Drivers\Platforms\Mysql\Mapping\Types;

use \Penumbra\Drivers\Platforms\Standard\Mapping;
use \Penumbra\Core\Object\Expressions as O;
use \Penumbra\Drivers\Base\Relational\Expressions as R;

class DateTimeZoneMapper extends Mapping\ObjectTypeMapper {
    
    public function GetClass() {
        return 'DateTimeZone';
    }
    
    public function MapValue(R\Expression $ValueExpression) {
        return $ValueExpression;
    }

    protected function MapClassInstance($Instance) {
        return R\Expression::BoundValue($Instance->getName());
    }
    
    protected function ReviveClassInstance($MappedValue) {
        return new \DateTimeZone($MappedValue);
    }

    protected function MapNewClass(array $MappedArgumentExpressions) {
        return $MappedArgumentExpressions[0];
    }

    protected function Map(R\Expression $ValueExpression, O\TraversalExpression $TraversalExpression) {
        switch ($this->IsMethodCall($TraversalExpression)) {
            
            case 'getName':
                return $ValueExpression;
        }
    }
}

?>