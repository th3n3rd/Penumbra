<?php

namespace Storm\Core\Mapping;

use \Storm\Core\Containers\Map;
use \Storm\Core\Containers\Registrar;
use \Storm\Core\Object;
use \Storm\Core\Object\IProperty;
use \Storm\Core\Relational;
use \Storm\Core\Relational\IColumn;

/**
 * {@inheritDoc}
 */
abstract class EntityRelationalMap implements IEntityRelationalMap {
    use \Storm\Core\Helpers\Type;    
    
    /**
     * @var Object\IEntityMap
     */
    private $EntityMap;
    
    /**
     * @var string
     */
    private $EntityType;
    
    /**
     * @var Relational\ITable
     */
    private $PrimaryKeyTable;
    
    /**
     * @var Relational\ITable[]
     */
    private $PersistTables;
    
    /**
     * @var Relational\ITable[]
     */
    private $ReviveTables;
    
    /**
     * @var IProperty[]
     */
    private $MappedProperties = [];
    
    /**
     * @var Relational\IColumn[]
     */
    private $MappedReviveColumns = [];
    
    /**
     * @var Relational\IColumn[]
     */
    private $MappedPersistColumns = [];
    
    /**
     * @var IPropertyMapping
     */
    private $PropertyMappings = [];
    
    /**
     * @var IDataPropertyColumnMapping[]
     */
    private $DataPropertyColumnMappings = [];
    
    /**
     * @var IDataPropertyColumnMapping[]
     */
    private $IdentityPropertyPrimaryKeyMappings = [];
    
    /**
     * @var IEntityPropertyToOneRelationMapping[]
     */
    private $EntityPropertyToOneRelationMappings = [];
    
    /**
     * @var ICollectionPropertyToManyRelationMapping[]
     */
    private $CollectionPropertyToManyRelationMappings = [];
    
    /**
     * {@inheritDoc}
     */
    final public function Initialize(DomainDatabaseMap $DomainDatabaseMap) {
        $this->OnInitialize($DomainDatabaseMap);
        
        $this->EntityMap = $this->EntityMap($DomainDatabaseMap->GetDomain());
        $this->EntityType = $this->EntityMap->GetEntityType();
        if(!($this->EntityMap instanceof Object\IEntityMap)) {
            throw new \Storm\Core\UnexpectedValueException(
                    'Return value from %s->EntityMap() must be an instance of %s, %s given', 
                    get_class($this), 
                    Object\IEntityMap::IEntityMapType, 
                    \Storm\Core\Utilities::GetTypeOrClass($this->EntityMap));
        }
        
        $Registrar = new Registrar(IPropertyMapping::IPropertyMappingType);
        $this->RegisterPropertyMappings($Registrar, $this->EntityMap, $DomainDatabaseMap->GetDatabase());
        
        foreach($Registrar->GetRegistered() as $PropertyMapping) {
            $this->AddPropertyMapping($PropertyMapping);
        }
        
        foreach($this->MappedReviveColumns as $MappedReviveColumn) {
            $Table = $MappedReviveColumn->GetTable();
            $this->ReviveTables[$Table->GetName()] = $Table;
        }
        foreach($this->MappedPersistColumns as $MappedPersistColumn) {
            $Table = $MappedPersistColumn->GetTable();
            $this->PersistTables[$Table->GetName()] = $Table;
        }
        if($this->PrimaryKeyTable === null) {
            throw new MappingException(
                    'The property mappings of %s must contain atleast 1 identity primary key mapping',
                    get_class($this));
        }
        
        $this->OnInitialized($DomainDatabaseMap);
    }
    
    public function InitializeRelationshipMappings(DomainDatabaseMap $DomainDatabaseMap) {
        foreach($this->EntityPropertyToOneRelationMappings + $this->CollectionPropertyToManyRelationMappings as $RelationshipMapping) {
            $RelatedEntityRelationalMap = $DomainDatabaseMap->GetEntityRelationalMap($RelationshipMapping->GetEntityType());
            $RelationshipMapping->SetEntityRelationalMap($RelatedEntityRelationalMap);
        }
    }
    
    /**
     * Event called pre initialization.
     * 
     * @param DomainDatabaseMap $DomainDatabaseMap The parent domain database map
     */
    protected function OnInitialize(DomainDatabaseMap $DomainDatabaseMap) { }
    /**
     * Event called post initialization.
     * 
     * @param DomainDatabaseMap $DomainDatabaseMap The parent domain database map
     */
    protected function OnInitialized(DomainDatabaseMap $DomainDatabaseMap) { }
    
    /**
     * The method to specify the entity map representing the entity properties of this
     * relational map.
     * 
     * @param Object\Domain The current domain
     * @return Object\IEntityMap
     */
    protected abstract function EntityMap(Object\Domain $Domain);
    
    /**
     * The method to register all the property mappings for this entity relational map.
     * 
     * @param Registrar $Registrar The registrar to register the property mappings
     * @param Object\IEntityMap $EntityMap The entity map representing the entity properties
     * @param Relational\Database $Database The current database instance
     * @return void
     */
    protected abstract function RegisterPropertyMappings(Registrar $Registrar, Object\IEntityMap $EntityMap, Relational\Database $Database);
    
    /**
     * Adds a property mapping to the entity relation map.
     * 
     * @param IDataPropertyColumnMapping|IEntityPropertyToOneRelationMapping|ICollectionPropertyToManyRelationMapping $PropertyMapping
     * @throws MappingException If the property mapping is not
     */
    private function AddPropertyMapping(IPropertyMapping $PropertyMapping) {
        $ProperyIdentifier = $PropertyMapping->GetProperty()->GetIdentifier();
        if($PropertyMapping instanceof IDataPropertyColumnMapping) {
            $this->DataPropertyColumnMappings[$ProperyIdentifier] = $PropertyMapping;
            $this->MappedReviveColumns = array_merge($this->MappedReviveColumns, $PropertyMapping->GetReviveColumns());
            $this->MappedPersistColumns = array_merge($this->MappedPersistColumns, $PropertyMapping->GetPersistColumns());
            if($PropertyMapping->IsIdentityPrimaryKeyMapping()) {
                $this->AddIdentityPrimaryKeyMapping($PropertyMapping);
            }
        }
        else if($PropertyMapping instanceof IEntityPropertyToOneRelationMapping) {
            $this->EntityPropertyToOneRelationMappings[$ProperyIdentifier] = $PropertyMapping;
        }
        else if($PropertyMapping instanceof ICollectionPropertyToManyRelationMapping) {
            $this->CollectionPropertyToManyRelationMappings[$ProperyIdentifier] = $PropertyMapping;
        }
        else {
            throw new MappingException('Supplied property mapping must be of type %s, %s or %s: %s given',
                    IDataPropertyColumnMapping::IDataPropertyColumnMappingType,
                    IEntityPropertyToOneRelationMapping::IEntityPropertyToOneRelationMappingType,
                    ICollectionPropertyToManyRelationMapping::ICollectionPropertyToManyRelationMappingType,
                    get_class($PropertyMapping));
        }
        
        $this->MappedProperties[$ProperyIdentifier] = $PropertyMapping->GetProperty();
        $this->PropertyMappings[$ProperyIdentifier] = $PropertyMapping;
    }
    
    private function AddIdentityPrimaryKeyMapping(IDataPropertyColumnMapping $PropertyMapping) {
        //Infer primary key table
        $AllColumns = array_merge($PropertyMapping->GetPersistColumns(), $PropertyMapping->GetReviveColumns());
        if($this->PrimaryKeyTable === null) {
            $this->PrimaryKeyTable = reset($AllColumns)->GetTable();
        }
        foreach($AllColumns as $Column) {
            if(!$Column->GetTable()->Is($this->PrimaryKeyTable)) {
                throw new MappingException('Identity properties of %s cannot map across multiple tables: %s.%s does not belong to %s',
                        $this->EntityType,
                        $Column->GetTable()->GetName(),
                        $Column->GetName(),
                        $this->PrimaryKeyTable->GetName());
            }
        }
        $this->IdentityPropertyPrimaryKeyMappings[] = $PropertyMapping;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetEntityMap() {
        return $this->EntityMap;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetPrimaryKeyTable() {
        return $this->PrimaryKeyTable;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetMappedProperties() {
        return $this->MappedProperties;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetMappedReviveTables() {
        return $this->ReviveTables;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetMappedPersistTables() {
        return $this->PersistTables;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetEntityType() {
        return $this->EntityType;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetPropertyMappings() {
        return $this->PropertyMappings;
    }
    
    final public function HasPropertyMapping($PropertyIdentifier) {
        return isset($this->PropertyMappings[$PropertyIdentifier]);
    }
    
    final public function GetPropertyMapping($PropertyIdentifier) {
        return isset($this->PropertyMappings[$PropertyIdentifier]) ?
                $this->PropertyMappings[$PropertyIdentifier] : null;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetDataPropertyColumnMappings() {
        return $this->DataPropertyColumnMappings;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetIdentityPropertyPrimaryKeyMappings() {
        return $this->IdentityPropertyPrimaryKeyMappings;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetEntityPropertyToOneRelationMappings() {
        return $this->EntityPropertyToOneRelationMappings;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetCollectionPropertyToManyRelationMappings() {
        return $this->CollectionPropertyToManyRelationMappings;
    }
    
    private $ResultRow = null;
    /**
     * {@inheritDoc}
     */
    public function ResultRow($ColumnData = []) {
        if($this->ResultRow === null) {
            $AllPersistColumns = call_user_func_array('array_merge', 
                    array_map(function($Table) { return $Table->GetColumns(); }, $this->PersistTables));
            $this->ResultRow = new Relational\ResultRow($AllPersistColumns, $ColumnData);
        }
        
        return $this->ResultRow->Another($ColumnData);
    }

    /**
     * {@inheritDoc}
     */
    final public function GetSelectSource(Relational\Database $Database) {
        $SelectSources = new Relational\ResultSetSources($this->PrimaryKeyTable);
        $this->SelectSources($SelectSources, $Database);
        
        return $SelectSources;
    }
    protected function SelectSources(Relational\ResultSetSources $SelectSources, Relational\Database $Database) { }

    /**
     * {@inheritDoc}
     */
    final public function GetSelectCriteria(Relational\Database $Database) {
        $RelationalCriteria = new Relational\Criteria();
        $this->SelectCriteria($RelationalCriteria, $Database);
        
        return $RelationalCriteria;
    }
    protected function SelectCriteria(Relational\Criteria $RelationalCriteria, Relational\Database $Database) { }
    
    /**
     * Verifies that the data property column mapping exists for the supplied property.
     * 
     * @param \Storm\Core\Object\IProperty $Property The mapped property
     * @return IDataPropertyColumnMapping The property mapping
     * @throws \Storm\Core\Exceptions\UnmappedPropertyException If property is not mapped
     */
    private function VerifyDataPropertyColumnMapping($Method, IProperty $Property) {
        $PropertyIdentifier = $Property->GetIdentifier();
        if(isset($this->DataPropertyColumnMappings[$PropertyIdentifier])) {
            return $this->DataPropertyColumnMappings[$PropertyIdentifier];
        }
        else {
            throw new UnmappedPropertyException(
                    'The supplied property to %s has not been mapped',
                    $Method);
        }
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetMappedReviveColumns(IProperty $Property) {
        return $this->VerifyDataPropertyColumnMapping(__METHOD__, $Property)->GetReviveColumns();
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetMappedPersistColumns(IProperty $Property) {
        return $this->VerifyDataPropertyColumnMapping(__METHOD__, $Property)->GetPersistColumns();
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetAllMappedReviveColumns(array $Properties = null) {
        if($Properties === null) {
            return $this->MappedReviveColumns;
        }
        $ColumnGroups = array_map([$this, 'GetMappedReviveColumns'], $Properties);
        $Columns = call_user_func_array('array_merge', $ColumnGroups);
        return $Columns;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function GetAllMappedPersistColumns(array $Properties = null) {
        if($Properties === null) {
            return $this->MappedPersistColumns;
        }
        $ColumnGroups = array_map([$this, 'GetMappedPersistColumns'], $Properties);
        $Columns = call_user_func_array('array_merge', $ColumnGroups);
        return $Columns;
    }
        
    /**
     * {@inheritDoc}
     */
    final public function MapIdentityToPrimaryKey(Object\Identity $Identity) {
        $Identity = [$Identity];
        $PrimaryKey = [$this->PrimaryKeyTable->PrimaryKey()];
        foreach($this->IdentityPropertyPrimaryKeyMappings as $Mapping) {
            $Mapping->Persist($Identity, $PrimaryKey);
        }
        
        return $PrimaryKey[0];
    }
    /**
     * {@inheritDoc}
     */
    final public function MapIdentitiesToPrimaryKeys(array $Identities) {
        $PrimaryKeys = array_map(function () { return $this->PrimaryKeyTable->PrimaryKey(); }, $Identities);
        foreach($this->IdentityPropertyPrimaryKeyMappings as $Mapping) {
            $Mapping->Persist($Identities, $PrimaryKeys);
        }
        
        return $PrimaryKeys;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function MapPrimaryKeyToIdentity(Relational\PrimaryKey $PrimaryKey) {
        $PrimaryKey = [$PrimaryKey];
        $Identity = [$this->EntityMap->Identity()];
        foreach($this->IdentityPropertyPrimaryKeyMappings as $Mapping) {
            $Mapping->Revive($PrimaryKey, $Identity);
        }
        
        return $Identity[0];
    }
    /**
     * {@inheritDoc}
     */
    final public function MapPrimaryKeysToIdentities(array $PrimaryKeys) {
        $Identities = array_map(function () { return $this->EntityMap->Identity(); }, $PrimaryKeys);
        foreach($this->IdentityPropertyPrimaryKeyMappings as $Mapping) {
            $Mapping->Revive($PrimaryKeys, $Identities);
        }
        
        return $Identities;
    }
    
    /**
     * {@inheritDoc}
     */
    final public function MapEntityToSelect(Relational\ResultSetSelect $Select, array $AlreadyKnownProperties = []) {
        $Properties = $this->MappedProperties;
        if(count($AlreadyKnownProperties) > 0) {
            foreach($AlreadyKnownProperties as $AlreadyKnownProperty) {
                unset($Properties[$AlreadyKnownProperty->GetIdentifier()]);
            } 
        }
        $this->MapPropetiesToSelect($Select, $Properties);
    }
        
    /**
     * {@inheritDoc}
     */
    final public function MapPropetiesToSelect(Relational\ResultSetSelect $Select, array $Properties = null) {
        if($Properties === null) {
            $Properties = $this->MappedProperties;
        }
        else {
            $Properties = array_combine(array_map(function ($Property) { return $Property->GetIdentifier(); }, $Properties), $Properties);
        }
        
        foreach($Properties as $PropertyIdentifier => $Property) {
            if(isset($this->DataPropertyColumnMappings[$PropertyIdentifier])) {
                $Select->AddColumns($this->DataPropertyColumnMappings[$PropertyIdentifier]->GetReviveColumns());
            }
            else if(isset($this->EntityPropertyToOneRelationMappings[$PropertyIdentifier])) {
                $this->EntityPropertyToOneRelationMappings[$PropertyIdentifier]->AddToRelationalSelect($Select);
            }
            else if(isset($this->CollectionPropertyToManyRelationMappings[$PropertyIdentifier])) {
                $this->CollectionPropertyToManyRelationMappings[$PropertyIdentifier]->AddToRelationalSelect($Select);
            }
        }
    }
    
    /**
     * {@inheritDoc}
     */
    final public function MapResultRowsToRevivalData(Relational\Database $Database, array $ResultRowArray) {
        return $this->MapResultRowsToRevivalDataByMappings($Database, $ResultRowArray, $this->PropertyMappings);
    }
    
    /**
     * {@inheritDoc}
     */
    final public function MapResultRowDataToRevivalData(array $ResultRowArray) {
        return $this->MapResultRowsToRevivalDataByMappings(null, $ResultRowArray, $this->DataPropertyColumnMappings);
    }
    
    private function MapResultRowsToRevivalDataByMappings(Relational\Database $Database = null, array $ResultRowArray, array $PropertyMappings) {
        $RevivalDataArray = [];
        foreach ($ResultRowArray as $Key => $ResultRow) {
            $RevivalData = $this->EntityMap->RevivalData();
            $RevivalDataArray[$Key] = $RevivalData;
        }
        
        foreach($PropertyMappings as $PropertyIdentifier => $PropertyMapping) {
            if($PropertyMapping instanceof IRelationshipPropertyRelationMapping) {
                $PropertyMapping->Revive($Database, $ResultRowArray, $RevivalDataArray);
            }
            else {
                $PropertyMapping->Revive($ResultRowArray, $RevivalDataArray);
            }
        }
        
        return $RevivalDataArray;
    }
}

?>