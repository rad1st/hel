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
                'name' => 'useravatar',
                'aliases' => array(),
                'group' => 'info',
                'description' => 'Posts the url of the avatar of the user (or yourself if no one).',
                'guildOnly' => true,
                'throttling' => array(
                    'usages' => 2,
                    'duration' => 3
                ),
                'args' => array(
                    array(
                        'key' => 'user',
                        'prompt' => 'Which user\'s avatar do you wanna see?',
                        'type' => 'user',
                        'default' => ''
                    )
                )
            ));
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            $user = (!empty($args['user']) ? $args['user'] : $message->message->author);
            return $message->say($user->getDisplayAvatarURL(1024));
        }
    });
};
