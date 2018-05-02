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
                'name' => 'botinfo',
                'aliases' => array(),
                'group' => 'info',
                'description' => 'Posts bot info.',
                'guildOnly' => false,
                'throttling' => array(
                    'usages' => 2,
                    'duration' => 3
                ),
                'guarded' => true
            ));
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            return $this->client->fetchUser('256418295685709824')->then(function ($author) use ($message) {
                if($message->message->guild && $message->message->guild->members->has($author->id)) {
                    $author = $message->message->guild->members->get($author->id)->__toString();
                } else {
                    $author = $author->tag;
                }
                
                $guildsCount = $this->client->guilds->count();
                $usersCount = $this->client->users->count();
                $channelsCount = $this->client->channels->count();
                
                $embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
                $embed->setAuthor($this->client->user->username, $this->client->user->getDisplayAvatarURL())->setColor(0xF74FB1)
                        ->addField('Version', '0.0.1', true)->addField('Library', 'CharlotteDunois/Livia', true)->addField('Author', (string) $author, true)
                        ->addField('Servers', (string) $guildsCount, true)->addField('Users', (string) $usersCount, true)->addField('Channels', (string) $channelsCount, true);
                
                return $message->say('', array('embed' => $embed));
            });
        }
    });
};
