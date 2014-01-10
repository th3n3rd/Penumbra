<?php

namespace Storm\Drivers\Platforms\Mysql;

use \Storm\Core\Relational;
use \Storm\Drivers\Base\Relational\Expressions\Expression;
use \Storm\Core\Relational\Expressions\Expression as CoreExpression;
use \Storm\Drivers\Base\Relational\Expressions as E;
use \Storm\Core\Relational\Expressions as EE;
use \Storm\Drivers\Base\Relational\Expressions\Operators as O;

final class FunctionMapper extends E\FunctionMapper {
    
    protected function MatchingFunctions() {
        return [
            'strtoupper' => 'UPPER',
            'strtolower' => 'LOWER',
            'strlen' => 'LENGTH',
            'strrev' => 'REVERSE',
            'chr' => 'CHAR',
            'ord' => 'ASCII',
            'base64_encode' => 'TO_BASE64',
            'base64_encode' => 'TO_BASE64',
            'bin2hex' => 'HEX',
            'hex2bin' => 'UNHEX',
            'soundex' => 'SOUNDEX',
            
            'time' => 'UNIX_TIMESTAMP',
            
            'abs' => 'ABS',
            'pow' => 'POW',
            'ceil' => 'CEIL',
            'floor' => 'FLOOR',
            'sqrt' => 'SQRT',
            'base_convert' => 'CONV',
            
            'crc32' => 'CRC32',
        ];
    }
    
    // <editor-fold defaultstate="collapsed" desc="String functions">
    
    public function substr(&$MappedName, array &$ArgumentExpressions) {
        $MappedName = 'SUBSTR';
        
        $ArgumentExpressions[1] = $this->Add($ArgumentExpressions, 1);
    }

    public function strpos(&$MappedName, array &$ArgumentExpressions, $CaseSensitive = true) {
        $MappedName = 'LOCATE';


        $HaystackStringExpression = $ArgumentExpressions[0];
        $NeedleStringExpression = $ArgumentExpressions[1];
        //Flip order
        $ArgumentExpressions[0] = $HaystackStringExpression;
        $ArgumentExpressions[1] = $NeedleStringExpression;
        if ($CaseSensitive) {
            $ArgumentExpressions[1] = $this->Binary($ArgumentExpressions[1]);
        }
        if (isset($ArgumentExpressions[2])) {
            $ArgumentExpressions[2] = $this->Add($ArgumentExpressions[2], 1);
        }


        return $this->Subtract($this->FunctionCall($MappedName, $ArgumentExpressions), 1);
    }


    public function stripos(&$MappedName, array &$ArgumentExpressions) {
        return $this->strpos($MappedName, $ArgumentExpressions, false);
    }


    public function trim(&$MappedName, array &$ArgumentExpressions, $Direction = 'BOTH') {
        $MappedName = 'TRIM';
        $TrimCharacters = isset($ArgumentExpressions[1]) ?

                $ArgumentExpressions[1] : Expression::Constant(" \t\n\r\0\x0B");


        if (!($TrimCharacters instanceof EE\ConstantExpression) ||

                strlen($TrimCharacters->GetValue()) !== 1) {
            throw new \Exception('Mysql does not support trimming multiple characters (only a specified string)');
        }


        $ArgumentExpressions = [
            Expression::Multiple(
                    [Expression::Keyword($Direction),
                        $TrimCharacters,
                        Expression::Keyword('FROM'),
                        $ArgumentExpressions[0]]
            )
        ];
    }


    public function rtrim(&$MappedName, array &$ArgumentExpressions) {
        return $this->trim($MappedName, $ArgumentExpressions, 'LEADING');
    }


    public function ltrim(&$MappedName, array &$ArgumentExpressions) {
        return $this->trim($MappedName, $ArgumentExpressions, 'TRAILING');
    }


    public function preg_match(&$MappedName, array &$ArgumentExpressions) {
        return Expression::BinaryOperation(
                        $ArgumentExpressions[1], 
                        O\Binary::MatchesRegularExpression, 
                        $this->Binary($ArgumentExpressions[0]));

    }

    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Date and time functions">
    
    public function strftime(&$MappedName, array &$ArgumentExpressions) {
        $MappedName = 'DATE_FORMAT';
        
        if(!isset($ArgumentExpressions[1])) {
            $this->MapFunctionCallExpression('time');
        }        
        $ArgumentExpressions[1] = $this->FunctionCall('FROM_UNIXTIME', [$ArgumentExpressions[1]]);
        
        $ArgumentExpressions = array_reverse($ArgumentExpressions);
    }

    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Security functions">
    
    private function RawOutput(&$MappedName, array &$ArgumentExpressions, $RawOutputIndex) {
        if(isset($ArgumentExpressions[$RawOutputIndex])) {
            $RawOutputExpression = $ArgumentExpressions[$RawOutputIndex];
            unset($ArgumentExpressions[$RawOutputIndex]);
            
            $Md5FunctionCall = $this->FunctionCall($MappedName, $ArgumentExpressions);
            
            return Expression::FunctionCall('IF', Expression::ValueList([
                $RawOutputExpression,
                $this->MapFunctionCallExpression('hex2bin', [$Md5FunctionCall]),
                $Md5FunctionCall
            ]));
        }
    }
    
    public function hash(&$MappedName, array &$ArgumentExpressions) {
        $HashNameExpression = $ArgumentExpressions[0];
        unset($ArgumentExpressions[0]);
        
        if(!($HashNameExpression instanceof EE\ConstantExpression)) {
            throw new \Exception('Hash algorithm must be a constant value');
        }
        $HashName = $HashNameExpression->GetValue();
        $DataExpression = $ArgumentExpressions[1];
        
        switch ($HashName) {
            case 'md5':
                return $this->MapFunctionCallExpression('md5', [$ArgumentExpressions]);
                
            case 'sha1':
                return $this->MapFunctionCallExpression('sha1', [$ArgumentExpressions]);
                
            case 'sha224':
            case 'sha256':
            case 'sha384':
            case 'sha512':
                $MappedName = 'SHA2';
                $ShaLength = (int)substr($HashName, 3);
                $ArgumentExpressions[0] = $DataExpression;
                $ArgumentExpressions[1] = Expression::Constant($ShaLength);

            default:
                throw new \Exception('Unsupported hash algorithm: ' . $HashName);
        }
        
        
        return $this->RawOutput($MappedName, $ArgumentExpressions, 2);
    }
            
    public function md5(&$MappedName, array &$ArgumentExpressions) {
        $MappedName = 'MD5';
        
        return $this->RawOutput($MappedName, $ArgumentExpressions, 1);
    }
    public function sha1(&$MappedName, array &$ArgumentExpressions) {
        $MappedName = 'SHA1';
        
        return $this->RawOutput($MappedName, $ArgumentExpressions, 1);
    }

    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Numeric functions">
    
    public function round(&$MappedName, array &$ArgumentExpressions) {
        $MappedName = 'ROUND';
        if(isset($ArgumentExpressions[2])) {
            throw new \Exception('Does not support rounding modes');
        }
    }
    
    private function RandomInt(CoreExpression $Minimum, CoreExpression $Maximum) {
        $DifferenceExpression = new E\BinaryOperationExpression(
                $Maximum, 
                O\Binary::Subtraction, 
                $Minimum);

        return $this->MapFunctionCallExpression('round', 
                [Expression::BinaryOperation(
                        $Minimum, 
                        O\Binary::Addition, 
                        Expression::BinaryOperation(
                                Expression::FunctionCall('RAND'), 
                                O\Binary::Multiplication, 
                                $DifferenceExpression))]);
    }
    public function RandomIntFromArguments(array &$ArgumentExpressions, $DefaultMinimum, $DefaultMaximum) {
        $Minimum = null;
        $Maximum = null;
        if(count($ArgumentExpressions) === 0) {
            $Minimum = new EE\ConstantExpression($DefaultMinimum);
            $Maximum = new EE\ConstantExpression($DefaultMaximum);
        }
        else {
            $Minimum = $ArgumentExpressions[0];
            $Maximum = $ArgumentExpressions[1];
        }
        return $this->RandomInt($Minimum, $Maximum);
    }
    
    public function rand(&$MappedName, array &$ArgumentExpressions) {
        return $this->RandomIntFromArguments($ArgumentExpressions, 0, getrandmax());
    }
    
    public function mt_rand(&$MappedName, array &$ArgumentExpressions) {
        return $this->RandomIntFromArguments($ArgumentExpressions, 0, mt_getrandmax());
    }

    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Misc functions">
    
    public function in_array(&$MappedName, array &$ArgumentExpressions) {
        return Expression::BinaryOperation(
                $ArgumentExpressions[0], 
                O\Binary::In, 
                $ArgumentExpressions[1]);
    }

    // </editor-fold>
    
    // <editor-fold defaultstate="collapsed" desc="Helpers">
    
    private function Add(CoreExpression $Expression, $Value) {
        return Expression::BinaryOperation(
                        $Expression, 
                O\Binary::Addition, 
                Expression::Constant($Value));
    }

    private function Subtract(CoreExpression $Expression, $Value) {
        return Expression::BinaryOperation(
                        $Expression, 
                O\Binary::Subtraction, 
                Expression::Constant($Value));
    }

    private function Binary(CoreExpression $Expression) {
        return $this->FunctionCall('BINARY', [$Expression]);
    }

    // </editor-fold>
}

?>