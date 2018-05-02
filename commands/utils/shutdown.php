<?php
/**
 * Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

return function ($client) {
    return (new class($client) extends \CharlotteDunois\Bots\Jibril\JibrilCommand {
        function __construct(\CharlotteDunois\Bots\Jibril\JibrilClient $client) {
            parent::__construct($client, array(
                'name' => 'shutdown',
                'aliases' => array(),
                'group' => 'utils',
                'description' => 'Shuts down the bot.',
                'guildOnly' => false,
                'ownerOnly' => true,
                'guarded' => true
            ));
            
            $this->boolean = $this->client->registry->types->get('boolean');
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            return $message->say(':white_check_mark: Ok')->then(function ($msg) {
                $this->client->writeLog('info', 'Shutting down...');
                
                (new \React\Promise\Promise(function (callable $resolve, callable $reject) {
                    $this->client->addTimer(2, $resolve);
                }))->then(function () {
                    $this->client->destroy()->done(null, array($this->client, 'handlePromiseRejection'));
                })->done(null, array($this->client, 'handlePromiseRejection'));
                
                return $msg;
            });
        }
    });
};
