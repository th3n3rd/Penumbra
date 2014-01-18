<?php

namespace Storm\Drivers\Base\Object\Properties;

use \Storm\Core\Object;

class CollectionProperty extends MultipleEntityProperty {
    protected function ReviveProxies(Object\Domain $Domain, $Entity, array $Proxies) {
        return new Collections\Collection($this->GetEntityType(), $Proxies);
    }
    
    protected function ReviveCallableProperty(Object\Domain $Domain, $Entity, callable $Callback) {
        return new Collections\LazyCollection($Domain, $this->GetEntityType(), $Callback, $this->ProxyGenerator);
    }
    
    protected function ReviveArrayOfRevivalData(Object\Domain $Domain, $Entity, array $RevivalDataArray) {
        $EntityType = $this->GetEntityType();
        return new Collections\Collection($EntityType, $Domain->ReviveEntities($EntityType, $RevivalDataArray));
    }
    
    protected function PersistRelationshipChanges(Object\Domain $Domain, Object\UnitOfWork $UnitOfWork,
            $ParentEntity, $CurrentValue, $HasOriginalValue, $OriginalValue) {
        $RelationshipChanges = array();
        
        $OriginalEntities = array();
        $CurrentEntities = array();
        
        if(!($CurrentValue instanceof Collections\ICollection)) {
            if(!($CurrentValue instanceof \Traversable)) {
                throw new \Exception;//TODO:error message
            }
            foreach($CurrentValue as $Entity) {
                if($this->IsValidEntity($Entity)) {
                    $CurrentEntities[] = $Entity;
                }
            }
        }
        else if($CurrentValue == $OriginalValue && !$CurrentValue->__IsAltered()) {
            return array();
        }
        else {
            $CurrentEntities = $CurrentValue->ToArray();
        }
        
        if($HasOriginalValue) {
            $OriginalEntities = $OriginalValue->ToArray();
        }
        $NewOrAlteredEntities = $this->ComputeDifference($CurrentEntities, $OriginalEntities);
        $RemovedEntities = $this->ComputeIdentityDifference($Domain, $OriginalEntities, $CurrentEntities);
        
        foreach($NewOrAlteredEntities as $NewEntity) {
            $RelationshipChanges[] = new Object\RelationshipChange(
                    $this->RelationshipType->GetPersistedRelationship(
                            $Domain, $UnitOfWork, 
                            $ParentEntity, $NewEntity), 
                    null);
        }
        foreach($RemovedEntities as $RemovedEntity) {
            $RelationshipChanges[] = new Object\RelationshipChange(
                    null, 
                    $this->RelationshipType->GetDiscardedRelationship(
                            $Domain, $UnitOfWork, 
                            $ParentEntity, $RemovedEntity));
        }
        
        return $RelationshipChanges;
    }
    protected function DiscardRelationshipChanges(Object\Domain $Domain, Object\UnitOfWork $UnitOfWork, 
            $ParentEntity, $CurrentValue, $HasOriginalValue, $OriginalValue) {
        
        $DiscardedRelationships = array();
        if($HasOriginalValue) {
            foreach($OriginalValue->ToArray() as $RemovedEntity) {
                $DiscardedRelationships[] = new Object\RelationshipChange(
                        null, 
                        $this->RelationshipType->GetDiscardedRelationship(
                                $Domain, $UnitOfWork, 
                                $ParentEntity, $RemovedEntity));
            }
        }
        
        return $DiscardedRelationships;
    }
    
    private function ComputeDifference(array $Objects, array $OtherObjects) {
        $Difference = array();
        foreach($Objects as $Object) {
            if(!in_array($Object, $OtherObjects)) {
                $Difference[] = $Object;
            }
        }
        
        return $Difference;
    }
    
    private function ComputeIdentityDifference(Object\Domain $Domain, array $Objects, array $OtherObjects) {
        $this->IndexEntitiesByIdentity($Domain, $Objects);
        $this->IndexEntitiesByIdentity($Domain, $OtherObjects);
        
        return array_diff_key($Objects, $OtherObjects);
    }
    
    private function IndexEntitiesByIdentity(Object\Domain $Domain, array &$Entities) {
        $IndexedEntities = array();
        foreach($Entities as $Key => $Entity) {
            $IndexedEntities[$Domain->Identity($Entity)->Hash()] = $Entity;
        }
        
        $Entities = $IndexedEntities;
    }
}

?>
