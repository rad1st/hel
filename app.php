<?php
/**
 * Hel based on Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 * Copyright 2018 rad1st, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

define('IN_DIR', str_replace('\\', '/', __DIR__));
require_once(IN_DIR.'/vendor/autoload.php');
ini_set('memory_limit', '-1');

if(getcwd() !== __DIR__) {
    chdir(__DIR__);
}

if(!realpath(__DIR__.'/logs/')) {
    mkdir(__DIR__.'/logs/');
}

$timer = null;

$configPath = IN_DIR.'/storage/config.json';
$config = json_decode(file_get_contents($configPath), true);
$token = file_get_contents(IN_DIR.'/storage/'.$config['tokenFile'].'.token');

$client = new \CharlotteDunois\Bots\Jibril\JibrilClient(array(
    'configPath' => $configPath,
    'disableClones' => array('channelUpdate', 'guildMemberUpdate', 'guildUpdate', 'roleUpdate', 'presenceUpdate', 'userUpdate', 'voiceStateUpdate'),
    'disableEveryone' => true,
    'messageCacheLifetime' => 3600,
    'messageSweepInterval' => 600,
    'ws.disabledEvents' => array('CHANNEL_PINS_UPDATE', 'GUILD_BAN_ADD', 'GUILD_BAN_REMOVE', 'GUILD_INTEGRATIONS_UPDATE', 'TYPING_START', 'VOICE_SERVER_UPDATE'),
    'commandPrefix' => $config['prefix'],
    'owners' => $config['owners'],
    'invite' => '',
    'commandBlockedMessagePattern' => false,
    'commandEditableDuration' => $config['commandEditableDuration'],
    'commandThrottlingMessagePattern' => false,
    'nonCommandEditable' => (bool) $config['nonCommandEditable'],
    'unknownCommandResponse' => (bool) $config['unknownCommandResponse']
));

$client->on('ready', function () use ($client, &$timer) {
    if($timer) {
        $client->cancelTimer($timer);
    }
    
    $client->writeLog('info', 'Logged in as '.$client->user->tag.' created on '.$client->user->createdAt->format('d.m.Y, H:i:s'));
    
    if(!empty($client->getConfig('gamePlaying'))) {
        $client->user->setGame($client->getConfig('gamePlaying').' | '.$client->getOption('commandPrefix').'help')->done(null, array($client, 'handlePromiseRejection'));
    }
});
$client->on('disconnect', function ($code, $reason) use ($client, &$timer) {
    $client->writeLog('warn', 'Disconnected! (Code: '.$code.' | Reason: '.$reason.')');
    
    $timer = $client->addTimer(30, function ($client) {
        if($client->getWSstatus() === \CharlotteDunois\Yasmin\Client::WS_STATUS_DISCONNECTED) {
            $client->writeLog('warn', 'Connection forever lost');
            $client->destroy()->done(null, array($client, 'handlePromiseRejection'));
        }
    });
});
$client->on('reconnect', function () use ($client) {
    $client->writeLog('info', 'Reconnecting...');
});

$client->registry->registerDefaults();

$client->registry->registerGroup(
    array('id' => 'eve', 'name' => 'Eve'),
    array('id' => 'info', 'name' => 'Info')
);
$client->registry->registerCommandsIn(IN_DIR.'/commands/');

$zkbcmd = $client->registry->findCommands('zkb', false);
$client->addPeriodicTimer(5, function () use ($zkbcmd) {
    $i = 0;
    while ($i < 12) {
        $i++;
        $zkbcmd[0]->tick();
    }
});
    
$client->login($token);
$client->getLoop()->run();
