<?php

namespace Storm\Api\Caching;

use \Storm\Api\Wrapper;
use \Storm\Api\Base;
use \Storm\Utilities\Cache;
use \Storm\Drivers\Base\Relational\IPlatform;

class Storm extends Base\Storm {
    const StormInstanceKey = 'Storm';
    private $Cache;
    private $EntityExpirySeconds;
    
    public function __construct(
            Cache\ICache $Cache, 
            IPlatform $Platform, 
            callable $StormConstructor,
            $EntityExpirySeconds = 300) {
        $this->Cache = $Cache;
        $this->EntityExpirySeconds = $EntityExpirySeconds;
        
        $Storm = $this->Cache->Retrieve(self::StormInstanceKey);
        
        if(!($Storm instanceof \Storm\Api\Base\Storm)) {
            $Storm = $StormConstructor();
            $this->Cache->Save(self::StormInstanceKey, $Storm);
        }
        
        $Storm->GetDomainDatabaseMap()->GetDatabase()->SetPlatform($Platform);
        
        parent::__construct($Storm->GetDomainDatabaseMap());
    }
    
    protected function ConstructRepository($EntityType, $AutoSave = false) {
        return new Repository(
                $this->GetDomainDatabaseMap(),
                $EntityType, 
                $AutoSave,
                $this->Cache,
                new Cache\DevelopmentCache(),
                $this->EntityExpirySeconds);
    }
}

?>
