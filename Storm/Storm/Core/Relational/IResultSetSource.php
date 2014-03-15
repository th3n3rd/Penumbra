<?php

namespace Storm\Core\Relational;

interface IResultSetSource {
    /**
     * @return boolean
     */
    public function HasColumn(IColumn $Column);
    
    /**
     * @return IColumn[]
     */
    public function GetColumns();
}

?>