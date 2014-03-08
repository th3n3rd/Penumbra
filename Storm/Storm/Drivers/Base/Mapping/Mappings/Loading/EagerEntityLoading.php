<?php

namespace Storm\Drivers\Base\Mapping\Mappings\Loading;

use \Storm\Core\Mapping;
use \Storm\Core\Object;
use \Storm\Core\Relational;

class EagerEntityLoading extends EntityLoading {
    public function AddToRelationalRequest(
            Mapping\IEntityRelationalMap $EntityRelationalMap, 
            Relational\IToOneRelation $ToOneRelation, 
            Relational\Select $RelationalRequest) {
        $this->MapEntityToRelationalRequest($EntityRelationalMap, $RelationalRequest);
    }

    public function Load(
            Mapping\IEntityRelationalMap $EntityRelationalMap, 
            Relational\Database $Database, 
            Relational\IToOneRelation $ToOneRelation, 
            array $ParentAndRelatedRowArray) {
        $this->UnsetNullRows($EntityRelationalMap, $ParentAndRelatedRowArray);
        //Maintains keys
        $RelatedRevivalData = $EntityRelationalMap->MapResultRowsToRevivalData($Database, $ParentAndRelatedRowArray);
        
        //Fill any unset rows with null
        return $RelatedRevivalData + array_fill_keys(array_keys($ParentAndRelatedRowArray), null);
    }
    
    private function UnsetNullRows(
            Mapping\IEntityRelationalMap $EntityRelationalMap, 
            array &$ResultRows) {
        $ReviveColumns = $EntityRelationalMap->GetAllMappedReviveColumns();
        foreach($ResultRows as $Key => $ResultRow) {
            $Data = array_intersect_key($ResultRow->GetData(), $ReviveColumns);
            if(array_filter($Data, 'is_null') === count($Data)) {
                unset($ResultRows[$Key]);
            }
        }
    }
}

?>