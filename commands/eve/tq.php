<?php
/**
 * Hel based on Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 * Copyright 2018 Rad1st, All Rights Reserved
*/

return function ($client) {
    return (new class($client) extends \CharlotteDunois\Bots\Jibril\JibrilCommand {
        function __construct(\CharlotteDunois\Bots\Jibril\JibrilClient $client) {
            parent::__construct($client, array(
                'name' => 'tq',
                'aliases' => array(),
                'group' => 'eve',
                'description' => 'Posts the current status of Tranquility.',
                'guildOnly' => false,
                'throttling' => array(
                    'usages' => 1,
                    'duration' => 5
                )
            ));
            
            $this->tq = array();
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            return $this->client->apimanager->get('ESI')->getTQStatus()->then(function ($json) use ($message) {
                $embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
		$embed->setColor(($json['players']==0 ? 0xfb6828 : 0x80bf65))->setTitle('EVE Online Server status');
		if (isset($json['error'])) {
                $embed->addField('__**Tranquility**__: ',
		    "Error: **".$json['error']."**", true);
		} else 
                $embed->addField('__**Tranquility**__: ',
		    "Start time: **".$json['start_time']."**\n".
		    "Players: **".$json['players']."**\n".
		    "Version: **".$json['server_version']."**", true);

                return $message->say('', array('embed' => $embed));
            });
        }

    });
};
