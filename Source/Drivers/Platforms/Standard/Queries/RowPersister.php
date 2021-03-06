<?php

namespace Penumbra\Drivers\Platforms\Standard\Queries;

use \Penumbra\Core\Relational;
use \Penumbra\Drivers\Base\Relational\Table;
use \Penumbra\Drivers\Base\Relational\Queries\ParameterType;
use \Penumbra\Drivers\Base\Relational\Queries\IConnection;
use \Penumbra\Drivers\Base\Relational\Queries\QueryBuilder;
use \Penumbra\Drivers\Base\Relational\Expressions as R;
use \Penumbra\Drivers\Platforms\Base\Queries\UpsertPersister;

abstract class RowPersister extends UpsertPersister {
    
    protected function InsertRowsIndividually(
            IConnection $Connection, 
            Relational\ITable $Table, 
            array $Rows, 
            callable $PostIndividualInsertCallback) {
        
        $TableName = $Table->GetName();
        $Columns = $Table->GetColumns();
        $ColumnNames = array_keys($Columns);
        
        $QueryBuilder = $Connection->QueryBuilder();
        $this->AppendInsert($QueryBuilder, $TableName, $ColumnNames);
        
        $QueryBuilder->Append('(');
        foreach($QueryBuilder->Delimit($Columns, ',') as $Column) {
            $QueryBuilder->AppendExpression($Column->GetPersistExpression(R\Expression::BoundValue(null)));
        }
        $QueryBuilder->Append(')');
        
        $PreparedInsert = $QueryBuilder->Build();
        $Bindings = $PreparedInsert->GetBindings();
        
        $ColumnValues = array_values($Columns);
        $ParameterTypes = $this->GetParamterTypes($ColumnValues);
        
        foreach($Rows as $Row) {
            foreach($ColumnValues as $Count => $Column) {
                $Value = $Row[$Column];
                $Bindings->Bind($Value, $Value === null ? ParameterType::Null : $ParameterTypes[$Count], $Count);
            }
            $PreparedInsert->Execute();
            $PostIndividualInsertCallback($Row);
        }
    }
    
    protected function AppendInsert(QueryBuilder $QueryBuilder, $TableName, $ColumnNames) {
        $QueryBuilder->AppendIdentifier('INSERT INTO #', [$TableName]);
        
        $QueryBuilder->AppendIdentifiers('(#) VALUES ', $ColumnNames, ',');
    }
    
    final protected function UpsertRows(IConnection $Connection, Relational\ITable $Table, array $Rows, $ShouldReturnKeyData) {
        $QueryBuilder = $Connection->QueryBuilder();
        
        $this->UpsertRowsQuery($QueryBuilder, $Table, $Rows, $ShouldReturnKeyData);
        
        $ExecutedQuery = $QueryBuilder->Build()->Execute();
        if($ShouldReturnKeyData) {
            return $ExecutedQuery->FetchAll();
        }
    }
    protected abstract function UpsertRowsQuery(
            QueryBuilder $QueryBuilder, 
            Relational\ITable $Table, 
            array $Rows, 
            $ShouldReturnKeyData);
    
    protected function AppendDataAsInlineTable(
            QueryBuilder $QueryBuilder, 
            array $Columns,
            $DerivedTableName,
            array $ColumnDataArray) {
        
        $QueryBuilder->Append(' SELECT ');
        
        /*
         * Apply all the persisting data expressions as a select on the inline table
         * rather than on every row
         */
        foreach($QueryBuilder->Delimit($Columns, ', ') as $ColumnName => $Column) {
            $QueryBuilder->AppendExpression(
                    $Column->GetPersistExpression(R\Expression::Identifier([$DerivedTableName, $ColumnName])));
        }

        $QueryBuilder->Append(' FROM (');

        $ColumnDataArray = array_map(
                function(Relational\ColumnData $ColumnData) { 
                    return $ColumnData->GetData();
                }, 
                $ColumnDataArray);

        $ColumnNames = array_map(function($Column) { return $Column->GetName(); }, $Columns);
        $Identifiers = array_combine($ColumnNames, 
                array_map(function($Column) { return $Column->GetIdentifier(); }, $Columns));
        $ParameterTypes = $this->GetParamterTypes($Columns);

        $First = true;
        $QueryBuilder->Append('SELECT ');
        foreach($QueryBuilder->Delimit($ColumnDataArray, ' UNION ALL SELECT ') as $ColumnData) {
            $FirstValue = true;
            foreach($Identifiers as $ColumnName => $Identifier) {
                if($FirstValue) $FirstValue = false;
                else 
                    $QueryBuilder->Append(',');

                $Value = isset($ColumnData[$Identifier]) ? $ColumnData[$Identifier] : null;
                $QueryBuilder->AppendSingleValue($Value, $Value === null ? ParameterType::Null : $ParameterTypes[$ColumnName]);

                if($First) {
                    $QueryBuilder->AppendIdentifier(' AS #', [$ColumnName]);
                }
            }
            $First = false;
        }

        $QueryBuilder->AppendIdentifier(') AS #', [$DerivedTableName]);
    }
    
    protected function AppendDataAsInlineRow(
            QueryBuilder $QueryBuilder, 
            array $Columns, 
            Relational\ColumnData $ColumnData) {
        
        $QueryBuilder->Append(' SELECT ');
        foreach($QueryBuilder->Delimit($Columns, ', ') as $Column) {
            $QueryBuilder->AppendExpression(
                    $Column->GetPersistExpression(R\Expression::BoundValue($ColumnData[$Column])));
            $QueryBuilder->AppendIdentifier(' AS #', [$Column->GetName()]);
        }
    }
    
    private function GetParamterTypes(array $Columns) {
        return array_combine(array_keys($Columns), array_map(
                function($Column) {
                    return $Column->GetDataType()->GetParameterType();
                },
                $Columns));
    }
    
    protected function DeleteRowBatch(
            IConnection $Connection, 
            Relational\ITable $Table, 
            array $PrimaryKeys) {
        $QueryBuilder = $Connection->QueryBuilder();
        
        $TableName = $Table->GetName();        
        $DerivedTableName = $TableName . 'PrimaryKeys';
        $TransformedDerivedTableName = $TableName . 'PersistencePrimaryKeys';
        
        $PrimaryKeysColumns = $Table->GetPrimaryKeyColumns();
        $PrimaryKeyNames = array_keys($PrimaryKeysColumns);
        
        $QueryBuilder->AppendIdentifier('DELETE # FROM # INNER JOIN (', [$TableName]);
        
        $this->AppendInlineData($QueryBuilder, $PrimaryKeysColumns, $DerivedTableName, $PrimaryKeys);
        
        $QueryBuilder->AppendIdentifier(') AS #', [$TransformedDerivedTableName]);   
        
        $QueryBuilder->Append(' ON ');
        
        foreach($QueryBuilder->Delimit($PrimaryKeyNames, ' AND ') as $PrimaryKeyName) {
            $QueryBuilder->AppendIdentifier('# = ', [$TableName, $PrimaryKeyName]);
            $QueryBuilder->AppendIdentifier('#', [$TransformedDerivedTableName, $PrimaryKeyName]);
        }
        
        $QueryBuilder->Build()->Execute();
    }
    
    protected function AppendInlineData(
            QueryBuilder $QueryBuilder , 
            array $Columns,
            $DerivedTableName,
            array $Data) {
        $this->AppendDataAsInlineTable($QueryBuilder, $Columns, $DerivedTableName, $Data);
    }
}

?>