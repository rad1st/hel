<?php
/**
 * Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

namespace CharlotteDunois\Bots\Jibril\API;

class BaseAPI {
    /** @var \CharlotteDunois\Bots\Jibril\APIManager */
    protected $manager;
    protected $key;
    
    function __construct(\CharlotteDunois\Bots\Jibril\APIManager $manager, $key) {
        $this->manager = $manager;
        $this->key = $key;
    }
}
