<?php

/**
 * Proxy Class for \StormExamples\One\Entities\Blog auto-generated by Storm.
 *                  --DO NOT MODIFY--
 */

namespace StormExamples\One\Domain\Maps\Proxies;

use Storm\Drivers\Base\Object\Properties\Proxies\IProxy as IProxy;
use Storm\Drivers\Base\Object\Properties\Proxies\EntityProxyFunctionality as ProxyFunctionality;

class StormExamples_One_Entities_Blog_Proxy extends \StormExamples\One\Entities\Blog implements IProxy {
    use ProxyFunctionality;
    
    public function __construct() {
        return call_user_func_array([$this, '__ConstructProxy'], func_get_args());
    }

       
    public function HelloWorld ($Hi) {
        $this->__Load();
        return parent::HelloWorld($Hi);
    }
}

?>