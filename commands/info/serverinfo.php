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
                'name' => 'serverinfo',
                'aliases' => array(),
                'group' => 'info',
                'description' => 'Posts info about the server.',
                'guildOnly' => true,
                'throttling' => array(
                    'usages' => 2,
                    'duration' => 3
                )
            ));
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            $guild = $message->message->guild;
            return $this->client->fetchUser($guild->ownerID)->then(function ($owner) use ($guild, $message) {
                switch($guild->verificationLevel) {
                    default:
                        $verifyLevel = 'None';
                    break;
                    case 1:
                        $verifyLevel = 'Low';
                    break;
                    case 2:
                        $verifyLevel = 'Medium';
                    break;
                    case 3:
                        $verifyLevel = 'Table Flip';
                    break;
                    case 4:
                        $verifyLevel = 'Double Table Flip';
                    break;
                }
                
                $textChannels = 0;
                $voiceChannels = 0;
                
                foreach($guild->channels as $channel) {
                    if($channel->type === 'text') {
                        $textChannels++;
                    } elseif($channel->type === 'voice') {
                        $voiceChannels++;
                    }
                }
                
                $roles = array();
                foreach($guild->roles as $role) {
                    $roles[] = $role->name;
                }
                \natsort($roles);
                
                $rolesString = "";
                foreach($roles as $role) {
                    $stringLength = \strlen($rolesString);
                    if(($stringLength + \strlen($role)) <= 1010) {
                        $rolesString .= ($stringLength === 0 ? '' : ', ').$role;
                    } else {
                        $rolesString .= ',...';
                    }
                }
                
                $icon = $guild->getIconURL();
                
                $embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
                $embed->setAuthor($guild->name, $icon)->setColor(0x00BBFF)->setThumbnail($icon)
                        ->addField('ID', $guild->id)
                        ->addField('Region', $guild->region, true)->addField('Verification Level', $verifyLevel, true)
                        ->addField('Channels', $textChannels.' Text | '.$voiceChannels.' Voice', true)->addField('Members', $guild->memberCount.' ('.$guild->members->count().' online)', true)
                        ->addField('Owner', $owner.' ('.$owner->tag.' | '.$owner->id.')')
                        ->addField('Guild Created', \CharlotteDunois\Bots\Jibril\Utils::formatDateTime($guild->createdAt))
                        ->addField('Roles ['.\count($roles).']', $rolesString);
                
                return $message->say('', array('embed' => $embed));
            });
        }
    });
};
