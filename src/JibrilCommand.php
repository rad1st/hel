<?php
/**
 * Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

namespace CharlotteDunois\Bots\Jibril;

abstract class JibrilCommand extends \CharlotteDunois\Livia\Commands\Command {
    protected $typings = array();
    
    function reload() {
        $reflector = new \ReflectionClass($this);
        $path = $reflector->getFileName();
        
        $name = \explode('/', \str_replace('\\', '/', $path));
        $name = \substr(\array_pop($name), 0, -4);
        
        $this->client->removeEventListenersByFilename($path);
        $this->client->clearIntervalsByName($name);
        $this->client->clearTimeoutsByName($name);
        
        return parent::reload();
    }
    
    function unload() {
        $reflector = new \ReflectionClass($this);
        $path = $reflector->getFileName();
        
        $name = \explode('/', \str_replace('\\', '/', $path));
        $name = \substr(\array_pop($name), 0, -4);
        
        $this->client->removeEventListenersByFilename($path);
        $this->client->clearIntervalsByName($name);
        $this->client->clearTimeoutsByName($name);
        
        return parent::unload();
    }
    
    function handleTyping(\CharlotteDunois\Livia\CommandMessage $message, bool $stop = false) {
        if(!empty($this->typings[$message->message->id])) {
            unset($this->typings[$message->message->id]);
            return;
        }
        
        if($stop === true || (\method_exists($message->message->channel, 'permissionsFor') && $message->message->channel->permissionsFor($message->client->user)->has('VIEW_CHANNEL') === false)) {
            return;
        }
        
        $rotations = 0;
        
        try {
            $this->typings[$message->message->id] = true;
            
            $fn = function () use (&$fn, $message, &$rotations) {
                if($rotations > 20) {
                    unset($this->typings[$message->message->id]);
                    return;
                }
                
                if(empty($this->typings[$message->message->id])) {
                    return;
                }
                
                $rotations++;
                
                $message->message->channel->startTyping();
                $this->client->addTimer(0.5, function () use ($message) {
                    $message->message->channel->stopTyping();
                });
                
                $this->client->addTimer(7, $fn);
            };
            
            $fn();
        } catch(\Exception $e) {
            /* Continue regardless of error */
        }
    }
}
