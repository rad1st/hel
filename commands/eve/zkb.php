<?php
/**
 * Hel based on Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 * Copyright 2018 Rad1st, All Rights Reserved
 */

return function ($client) {
    return (new class($client) extends \CharlotteDunois\Bots\Jibril\JibrilCommand {
        /** @var \React\MySQL\Connection */
        protected $db;
        
        function __construct(\CharlotteDunois\Bots\Jibril\JibrilClient $client) {
            
            $this->db = new React\MySQL\Connection($client->getLoop(), array(
                'dbname' => 'eve_sde',
                'user'   => 'bots',
                'passwd' => '',
            ));
            
            $this->db->connect(function () {});
            
            parent::__construct($client, array(
                'name' => 'zkb',
                'aliases' => array(),
                'group' => 'eve',
                'description' => 'Posts live killmails from zkb.',
                'guildOnly' => FALSE,
                'throttling' => array(
                    'usages' => 1,
                    'duration' => 5
                )
            ));
            
            $this->zkb = array();
        }

        /**
         * Runs a SQL query. Resolves with the Command instance.
         * @param string  $sql
         * @param array   $parameters  Parameters for the query - these get escaped
         * @return \React\Promise\ExtendedPromiseInterface
         * @see https://github.com/bixuehujin/reactphp-mysql/blob/master/src/Command.php
         */
        function runQuery(string $sql, array $parameters = array()) {
            return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($sql, $parameters) {
                if(!empty($parameters)) {
                    $query = new \React\MySQL\Query($sql);
                    $query->bindParamsFromArray($parameters);
                    $sql = $query->getSql();
                }
                
                $this->db->query($sql, function ($command) use ($resolve, $reject) {
                    if($command->hasError()) {
                        return $reject($command->getError());
                    }
                    $resolve($command);
                });
            }));
        }

        function tick() {
	    return \CharlotteDunois\Yasmin\Utils\URLHelpers::resolveURLToData('https://redisq.zkillboard.com/listen.php?queueID=helbot#1074')->then(function ($response){
                $raw = \json_decode((string) $response, true);
                if ($raw['package']=== NULL) {
//                    $this->client->writeLog('debug', 'ZKB queue empty');
                    return NULL;
                }
                $killmail = &$raw['package']['killmail'];
                $zkbkm = &$raw['package']['zkb'];
                
                $_ships = array();
                $_ships[] = (int) @$killmail['victim']['ship_type_id'];
                foreach ($killmail['attackers'] as $attacker) {
                    $_ships[] = (int) @$attacker['ship_type_id'];
                }

                $sql_getsysteminfo = 'SELECT mapRegions.regionName AS region, mapSolarSystems.solarSystemName AS name, mapSolarSystems.security AS security FROM `mapSolarSystems`
                    LEFT JOIN mapRegions ON mapRegions.regionID=mapSolarSystems.regionID WHERE mapSolarSystems.solarSystemID = ?';
                
                $sql_getitems = 'SELECT invTypes.typeID, invTypes.typeName, invTypes.groupID FROM `invTypes` WHERE FIND_IN_SET(invTypes.typeID, ?)';
                
                return \React\Promise\all([
                    'system' => $this->runQuery ($sql_getsysteminfo, array($killmail['solar_system_id']))->then(function($command) {return $command->resultRows;}),
                    'ship' => $this->runQuery ($sql_getitems, array(implode (',', $_ships)))->then(function($command) {return $command->resultRows;}),
                    ])->then(function($rows) use (&$killmail, &$zkbkm) {
                        
                        $sqlres = array();
                        $sqlres['system'] = $rows['system'][0];
                        foreach ($rows['ship'] as $ship) {
                            $sqlres['ship'][$ship['typeID']] = array (
                                'typeName' => $ship['typeName'],
                                'groupID' => $ship['groupID']
                            );
                        }
                        
                        $report = NULL;
                        foreach ($this->client->getConfig('zkb') as $group) {
                            $_corp = isset($group["corporations"]);
                            $_alliance = isset($group["alliances"]);
                            $_group = isset($group["groups"]);
                            $_value = isset($group["minimumValue"]);
                            
                            $hit_ca = FALSE;
                            $hit_group = FALSE;
                            $hit_value = FALSE;
                            
                            if ($_value && ((int)$zkbkm["totalValue"] > (int)$group["minimumValue"])) {
                                $hit_value = TRUE;
                            }
                            
                            // check victim
                            if ($_corp && in_array((int) @$killmail['victim']['corporation_id'], $group["corporations"])) {
                                $hit_ca = TRUE;
                            } elseif ($_alliance && in_array((int) @$killmail['victim']['alliance_id'], $group["alliances"])) {
                                $hit_ca = TRUE;
                            }
                            if ($_group && in_array((int) @$sqlres['ship'][$killmail['victim']['ship_type_id']]['groupID'], $group["groups"])) {
                                $hit_group = TRUE;
                            }
                            
                            $loss = TRUE;
                            $kill = FALSE;
                            if (($_corp || $_alliance) && !$hit_ca) {
                                $loss = FALSE;
                            }
                            if ($_group && !$hit_group) {
                                $loss = FALSE;
                            }
                            
                            if (!$loss) {
                                foreach ($killmail['attackers'] as $attacker) {
                                    // check attackers
                                    $kill = TRUE;
                                    $hit_ca = FALSE;
                                    $hit_group = FALSE;
                                    
                                    if ($_corp && in_array((int) @$attacker['corporation_id'], $group["corporations"])) {
                                        $hit_ca = TRUE;
                                    } elseif ($_alliance && in_array((int) (int) @$attacker['alliance_id'], $group["alliances"])) {
                                        $hit_ca = TRUE;
                                    }
                                    if ($_group && in_array((int) @$sqlres['ship'][$attacker['ship_type_id']]['groupID'], $group["groups"])) {
                                        $hit_group = TRUE;
                                    }
                                    if (($_corp || $_alliance) && !$hit_ca) {
                                        $kill = FALSE;
                                    }
                                    if ($_group && !$hit_group) {
                                        $kill = FALSE;
                                    }
                                    if ($kill) break;
                                }
                            }
                            
                            $_done = $loss || $kill;
                            
                            if ($_value && !$hit_value) {
                                $_done = FALSE;
                            }
                            
                            if ($_done) {
                                //                        $this->client->writeLog('debug', 'ZKB met conditions');
                                $report = Array();
                                $report["lossmail"] = $loss;
                                $report["region"] = $sqlres['system']['region'];
                                $report["systemname"] = $sqlres['system']['name'];
                                $report["security"] = number_format((float)$sqlres['system']['security'], 2, '.', '');
                                $report["description"] = $group["description"];
                                $report["channel"] = $group["channel"];
                                $report["killmail_id"] = $killmail["killmail_id"];
                                $report["killmail_time"] = $killmail["killmail_time"];
                                $report["totalValue"] = $zkbkm["totalValue"];
                                $report["victim"]=$killmail['victim'];
                                //lookup for final blow
                                $finalblow = NULL;
                                foreach ($killmail["attackers"] as $attacker) {
                                    if (@$attacker["final_blow"] == 1) {
                                        $report["finalblow"] = $attacker;
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                        if ($report !== NULL) {
                            
                            $httpclient = new \GuzzleHttp\Client(['base_uri' => 'https://esi.tech.ccp.is/latest/', 'proxy' => 'socks5h://127.0.0.1:9050']);
                            $name = function($response){
                                $json = \json_decode((string) $response->getBody(), TRUE);
                                return @$json['name'];
                            };
                            
                            $promises = [
                                'victim_chr_name' => @$httpclient->getAsync('characters/'.$killmail['victim']['character_id'].DS)->then($name),
                                'victim_corp_name' => @$httpclient->getAsync('corporations/'.$killmail['victim']['corporation_id'].DS)->then($name),
                                'victim_alliance_name' => @$httpclient->getAsync('alliances/'.$killmail['victim']['alliance_id'].DS)->then($name),
                                'fb_chr_name' => @$httpclient->getAsync('characters/'.$report['finalblow']['character_id'].DS)->then($name),
                                'fb_corp_name' => @$httpclient->getAsync('corporations/'.$report['finalblow']['corporation_id'].DS)->then($name),
                                'fb_alliance_name' => @$httpclient->getAsync('alliances/'.$report['finalblow']['alliance_id'].DS)->then($name)
                            ];
                            $results = GuzzleHttp\Promise\settle($promises)->wait();
                            $report['victim']['character_name'] = @$results['victim_chr_name']['value'];
                            $report['victim']['corporation_name'] = @$results['victim_corp_name']['value'];
                            $report['victim']['alliance_name'] = @$results['victim_alliance_name']['value'];
                            $report['victim']['ship_name'] = @$sqlres['ship'][$killmail['victim']['ship_type_id']]['typeName'];
                            $report['finalblow']['character_name'] = @$results['fb_chr_name']['value'];
                            $report['finalblow']['corporation_name'] = @$results['fb_corp_name']['value'];
                            $report['finalblow']['alliance_name'] = @$results['fb_alliance_name']['value'];
                            $report['finalblow']['ship_name'] = @$sqlres['ship'][$report['finalblow']['ship_type_id']]['typeName'];
                            
                        }
                        return $report;
                    }, function ($reason){ // sql error
                        $this->client->writeLog('Error '. $reason->getCode() . ': ' . $reason->getMessage());
                    });
            })->then(function ($km) {
                $channel = $this->client->channels->get($km["channel"]);
                // Making sure the channel exists
                if($channel) {
                    $embed = new \CharlotteDunois\Yasmin\Models\MessageEmbed();
                    
                    $victim = &$km['victim'];
                    $finalblow = &$km['finalblow'];
                    
                    $ship = @$victim['ship_name'];
                    $pilot = @$victim['character_name'];
                    $system = @$km['systemname'];
                    $ss = @$km['security'];
                    $region = @$km['region'];
                    $owner = (@$victim['alliance_name'] != NULL) ? @$victim['alliance_name'] : @$victim['corporation_name'];
                    //lookup for final blow
                    $fbship = @$finalblow['ship_name'];
                    $fbpilot = @$finalblow['character_name'];
                    $fbowner = (@$finalblow['alliance_name'] != NULL) ? @$finalblow['alliance_name'] : @$finalblow['corporation_name'];

                    $_pilot = ($pilot !== NULL) ? '**'.$pilot.'** ' : NULL;
                    $_tpilot = ($pilot !== NULL) ? $pilot.' | ' : NULL;
                    $_tfb = ($fbpilot !== NULL) ? 'Final Blow by **'.$fbpilot.'** ('.$fbowner.') flying in a **'.$fbship.'**.'
                        : 'Final Blow by **'.$fbship.'** ('.$fbowner.').';
                    $embed
                    ->setTitle($ship .' | '.$_tpilot.'Killmail')
#                   ->setColor(random_int(0, 16777215))
                    ->setColor($km['lossmail'] ? 0xfb6828 : 0x80bf65)
                    ->setDescription($_pilot.'('.$owner.') lost **'.$ship.'** in __'.$system.'__ ('.$ss.'){'.$region.'}.'.PHP_EOL.$_tfb)
                    ->setThumbnail('https://imageserver.eveonline.com/Type/'.$km['victim']['ship_type_id'].'_64.png')
                    ->setTimestamp(strtotime(@$km['killmail_time']))
                    ->setAuthor($km["description"], 'https://zkillboard.com/img/wreck.png')
                    ->setFooter('Total Value: '.number_format(@$km['totalValue']).' ISK')
                    ->setURL('https://zkillboard.com/kill/'.@$km['killmail_id'].'/');                               // Set the URL
                    
                    return $channel->send('', array('embed' => $embed))
                    ->otherwise(function ($error) {
                        $this->client->writeLog('debug', 'ZKB error'.$error);
                    });
                }
            });
        }
        
        function run(\CharlotteDunois\Livia\CommandMessage $message, \ArrayObject $args, bool $fromPattern) {
            return $message->reply('Command `zkb` running by timer.');
//          return $this->tick();
        }
        
    });
};
