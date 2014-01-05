<?php

namespace Storm\Drivers\Platforms\Development\Logging;

use Storm\Drivers\Base\Relational\Queries;

class Query implements Queries\IQuery {
    private $Logger;
    private $Query;
    
    public function __construct(ILogger $Logger, Queries\IQuery $Query) {
        $this->Logger = $Logger;
        $this->Query = $Query;
    }

    public function Execute() {
        $Bindings = $this->GetBindings();
        
        $this->Logger->Log(
                'Executing Query: ' . $this->InterpolateQuery($this->GetQueryString(), $Bindings->Get()));
        
        return $this->Query->Execute();
    }
    
   /**
    * Replaces any parameter placeholders in a query with the value of that
    * parameter. Useful for debugging. Assumes anonymous parameters from 
    * $params are are in the same order as specified in $query
    *
    * @param string $Query The sql query with parameter placeholders
    * @param Queries\Binding[] $Params The array of substitution parameters
    * @return string The interpolated query
    */
   public function InterpolateQuery($Query, array $Params) {
       $QuerySegments = explode('?', $Query);
       $InterpolatedQuery = array_shift($QuerySegments);
       $Count = 0;
       foreach ($Params as $Key => $Binding) {
           $ParameterType = $Binding->GetParameterType();
           $Value = $Binding->GetValue();
           switch ($ParameterType) {
               case Queries\ParameterType::String:
                   $Value = "'" . $Value . "'";
                   break;
               case Queries\ParameterType::Integer:
                   $Value = (string)$Value;
                   break;
               case Queries\ParameterType::Binary:
                   $Value = "'" . $Value . "'";
                   break;
               case Queries\ParameterType::Boolean:
                   $Value = $Value ? 'TRUE' : 'FALSE';
                   break;
               case Queries\ParameterType::Null:
                   $Value = 'NULL';
                   break;
               default:
                   throw new \Exception;
           }
           
           $InterpolatedQuery .= $Value . array_shift($QuerySegments);
           $Count++;
       }
       
       return $InterpolatedQuery;
   }
    
    public function GetQueryString() {
        return $this->Query->GetQueryString();
    }

    public function FetchAll() {
        return $this->Query->FetchAll();
    }

    public function FetchRow() {
        return $this->Query->FetchRow();
    }

    public function GetBindings() {
        return $this->Query->GetBindings();
    }

    public function SetBindings(Queries\Bindings $Bindings) {
        return $this->Query->SetBindings($Bindings);
    }

}

?>