<?php
/**
 * Yasmin
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

return function ($client) {
    return (new class($client) extends \CharlotteDunois\Bots\Jibril\JibrilCommand {
        function __construct(\CharlotteDunois\Bots\Jibril\JibrilClient $client) {
            parent::__construct($client, array(
                'name' => 'servertime',
                'aliases' => array(),
                'group' => 'info',
                'description' => 'Posts servertime (`--u` returns UNIX timestamp, `--up` returns uptime).',
                'guildOnly' => false,
                'throttling' => array(
                    'usages' => 2,
                    'duration' => 3
                )
            ));
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            if(\strpos($message->message->content, '--up') !== false) {
                $msg = ':clock9: Server Uptime: '.\CharlotteDunois\Bots\Jibril\Utils::calculateTimeElapsed((\time() - $this->client->startup));
            } elseif(\strpos($message->message->content, '--u') !== false) {
                $msg = ':clock9: Server Timestamp: '.\time();
            } else {
                $msg = ':clock9: Server Time: '.\date('r');
            }
            
            return $message->say($msg);
        }
    });
};
