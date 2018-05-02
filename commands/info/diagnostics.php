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
                'name' => 'diagnostics',
                'aliases' => array(),
                'group' => 'info',
                'description' => 'Posts bot diagnostics.',
                'guildOnly' => false,
                'ownerOnly' => true,
                'guarded' => true
            ));
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            $guildsCount = $this->client->guilds->count();
            $usersCount = $this->client->users->count();
            $channelsCount = $this->client->channels->count();
            
            $embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
            $embed->setAuthor($this->client->user->username.' Diagnostics', $this->client->user->getDisplayAvatarURL())->setColor(0x00A1FF)
                    ->addField('Uptime', \CharlotteDunois\Bots\Jibril\Utils::calculateTimeElapsed((\time() - $this->client->startup), ':', true), true)->addField('Commands called', $this->client->getCommandsCalled(), true)->addField('Avg. Ping', $this->client->getPing().'ms', true)
                    ->addField('CPU', (string) $this->getServerload(), true)->addField('Memory Usage', \ceil(\memory_get_usage(true) / 1014 / 1024).'MB', true)->addField('Shard', '0/0', true)
                    ->addField('Servers', (string) $guildsCount, true)->addField('Users', (string) $usersCount, true)->addField('Channels', (string) $channelsCount, true);
            
            return $message->say('', array('embed' => $embed));
        }
        
        function getServerload() {
            $serverload = array();
            
            if(\DIRECTORY_SEPARATOR != '\\') {
                $serverload = \sys_getloadavg();
                $serverload[0] = \round($serverload[0], 4);
                
                if(!\is_numeric($serverload[0])) {
                    return 'N/A';
                }
            } else {
                \exec("wmic cpu get loadpercentage /all", $output);
                if(!empty($output)) {
                    foreach($output as $line) {
                        if((!empty($line) OR \is_numeric($line)) AND \preg_match("/^[0-9]+\$/", $line)) {
                            $serverload[0] = $line;
                            break;
                        }
                    }
                }
                
                $serverload[0] .= "%";
            }
            
            if($serverload[0] == '0') {
                $serverload[0] == '0.0';
            }
            
            return \trim($serverload[0]);
        }
    });
};
