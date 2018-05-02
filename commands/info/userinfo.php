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
                'name' => 'userinfo',
                'aliases' => array(),
                'group' => 'info',
                'description' => 'Posts info about the user (or yourself if no one).',
                'guildOnly' => true,
                'throttling' => array(
                    'usages' => 2,
                    'duration' => 3
                ),
                'args' => array(
                    array(
                        'key' => 'user',
                        'prompt' => 'Which user\'s info do you wanna see?',
                        'type' => 'member',
                        'default' => ''
                    )
                )
            ));
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            $member = (!empty($args['user']) ? $args['user'] : $message->message->member);
            
            $icon = $member->user->getDisplayAvatarURL();
            $nickname = $member->nickname ?? '*None*';
            
            $status = ($member->presence && $member->presence->status ? $member->presence->status : 'offline');
            if($status === 'dnd') {
                $status = 'DND';
            }
            
            $roles = array();
            foreach($member->roles as $role) {
                $roles[] = $role->name;
            }
            \natsort($roles);
            
            $rolesString = "";
            foreach($roles as $role) {
                $stringLength = \mb_strlen($rolesString);
                if(($stringLength + \mb_strlen($role)) <= 1010) {
                    $rolesString .= ($stringLength === 0 ? '' : ', ').$role;
                } else {
                    $rolesString .= ',...';
                }
            }
            
            $embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
            $embed->setAuthor($member->user->tag, $icon)->setColor(0x61FF00)->setThumbnail($icon)
                    ->addField('ID', $member->id, true)->addField('Tag', $member->user->tag, true)
                    ->addField('Status', \ucfirst($status), true)->addField('Nickname', $nickname, true)
                    ->addField('Account Created', \CharlotteDunois\Bots\Jibril\Utils::formatDateTime($member->user->createdAt))
                    ->addField('Guild Joined', \CharlotteDunois\Bots\Jibril\Utils::formatDateTime($member->joinedAt))
                    ->addField('Roles ['.\count($roles).']', $rolesString);
            
            if($member->presence !== null && $member->presence->activity !== null) {
                $activity = \ucfirst(\CharlotteDunois\Yasmin\Models\Activity::TYPES[$member->presence->activity->type]);
                $embed->setDescription(($activity === 'Listening' ? 'Listening to' : $activity).' **'.$member->presence->activity->name.'**');
            }
            
            return $message->say('', array('embed' => $embed));
        }
    });
};
