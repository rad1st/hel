<?php
/**
 * Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

namespace CharlotteDunois\Bots\Jibril;

/** @property \CharlotteDunois\Bots\Jibril\JibrilClient  $client */
class APIManager {
    protected $client;
    
    protected $apis;
    protected $files = array();
    
    function __construct(\CharlotteDunois\Bots\Jibril\JibrilClient $client) {
        $this->client = $client;
        
        $this->apis = new \CharlotteDunois\Yasmin\Utils\Collection();
        $this->reloadFiles();
    }
    
    /**
     * @param $name
     * @return \CharlotteDunois\Bots\Jibril\JibrilClient
     * @throws \RuntimeException
     */
    function __get($name) {
        if($name === 'client') {
            return $this->client;
        }
        
        throw new \RuntimeException('Unknown property \CharlotteDunois\Bots\Jibril\APIManager::'.$name);
    }
    
    function reloadFiles() {
        $this->files = \glob(IN_DIR.'/inc/api/*.php');
        foreach($this->files as &$file) {
            $file = \str_replace(array(IN_DIR.'/inc/API/', '.php'), '', \str_replace('\\', '/', $file));
        }
    }
    
    /**
     * @return string[]
     */
    function getAvailableAPIs() {
        return $this->files;
    }
    
    /**
     * @return bool
     */
    function hasAPI(string $name) {
        return \in_array($name, $this->files);
    }
    
    /**
     * @return bool
     */
    function isInitialized(string $name) {
        return $this->apis->has($name);
    }
    
    /**
     * @throws \RuntimeException
     */
    function initialize(string $name, ?string $key = null) {
        if($this->isInitialized($name)) {
            throw new \RuntimeException('API is already initialized');
        }
        
        if($name === 'BaseAPI') {
            throw new \RuntimeException('You can not initialize the base API');
        }
        
        try {
            $ns = '\\CharlotteDunois\\Bots\\Jibril\\API\\'.$name;
            if(!\in_array('CharlotteDunois\\Bots\\Jibril\\API\\BaseAPI', \class_parents($ns, true))) {
                throw new \LogicException('Class does not extend BaseAPI');
            }
            
            $api = new $ns($this, $key);
            $this->apis->set($name, $api);
        } catch(\LogicException $error) {
            throw new \RuntimeException($error->getMessage());
        } catch(\Throwable | \Exception | \Error $error) {
            throw new \RuntimeException('Unknown API');
        }
    }
    
    /**
     * @return \CharlotteDunois\Bots\Jibril\API\BaseAPI|null
     */
    function get(string $name) {
        return $this->apis->get($name);
    }
    
    /**
     * @return \React\Promise\PromiseInterface
     * @throws \RuntimeException
     */
    function destroy(string $name) {
        if($this->isInitialized($name) === false) {
            throw new \RuntimeException('API is not initialized');
        }
        
        $api = $this->apis->set($name);
        if(\method_exists($api, 'destroy')) {
            $rt = $api->destroy();
            if(!($rt instanceof \React\Promise\PromiseInterface)) {
                $rt = \React\Promise\resolve();
            } else {
                $rt = $rt->then(function () {
                    return null;
                });
            }
        } else {
            $rt = \React\Promise\resolve();
        }
        
        unset($api);
        $this->apis->delete($name);
        
        return $rt;
    }
    
    /**
     * @return \React\Promise\PromiseInterface
     */
    function destroyAll() {
        $rt = array();
        
        foreach($this->apis as $name => $api) {
            if(\method_exists($api, 'destroy')) {
                $rtp = $api->destroy();
                if($rtp instanceof \React\Promise\PromiseInterface) {
                    $rt[] = $rtp->then(function () {
                        return null;
                    });
                }
            }
            
            unset($api);
            $this->apis->delete($name);
        }
        
        return \React\Promise\all($rt);
    }
}
