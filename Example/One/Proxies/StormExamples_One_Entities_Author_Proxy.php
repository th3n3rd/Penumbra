<?php

/**
 * Proxy Class for \StormExamples\One\Entities\Author auto-generated by Storm.
 *                  --DO NOT MODIFY--
 */

namespace StormExamples\One\Proxies;

use Storm\Drivers\Base\Object\Properties\Proxies\IProxy as IProxy;
use Storm\Drivers\Base\Object\Properties\Proxies\EntityProxyFunctionality as ProxyFunctionality;

class StormExamples_One_Entities_Author_Proxy extends \StormExamples\One\Entities\Author implements IProxy {
    use ProxyFunctionality;
    
    public function __construct() {
        return call_user_func_array([$this, '__ConstructProxy'], func_get_args());
    }

    
}

?>