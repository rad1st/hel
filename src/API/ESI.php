<?php
/**
 * Hel based on Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 * Copyright 2018 Rad1st, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

namespace CharlotteDunois\Bots\Jibril\API;

DEFINE ('DS','?datasource=tranquility');

class ESI extends \CharlotteDunois\Bots\Jibril\API\BaseAPI {
    protected $base = 'https://esi.tech.ccp.is/latest/';
    protected $remaining = -1;
    protected $reset = 0;
    
    protected $queue = array();
    protected $running = false;
    
    function getRemaining() {
        return $this->remaining;
    }
    
    function getReset() {
        return $this->reset;
    }
    
    // https://esi.tech.ccp.is/latest/status/?datasource=tranquility
    function getTQStatus() {
        return $this->queue('GET', 'status/'.DS);
    }
    
//    {}/characters/{}/'.format(ESI_URL, character_id)
    function character_info($IDs) {
        echo 'characters/'.$IDs.'/'.DS.PHP_EOL;
        return $this->queue('GET', 'characters/'.$IDs.'/'.DS);
    }
    
    function characters_names($IDs) {
        return $this->queue('GET', 'characters/names/'.$IDs.'/'.DS);
    }

    function queue(string $method, string $url, $data = null, array $files = array()) {
        return (new \React\Promise\Promise(function (callable $resolve, callable $reject) use ($method, $url, $data, $files) {
            $this->queue[] = \compact('method', 'url', 'data', 'files', 'resolve', 'reject');
            $this->startQueue();
        }));
    }
    
    function startQueue() {
        if($this->running === true) {
            return;
        }
        
        if($this->remaining === 0 && $this->reset > \time()) {
            $time = (\time() - $this->reset);
            $this->manager->client->writeLog('debug', 'Suspending ESI Queue processing due to ratelimits for '.$time.' seconds');
            
            return $this->manager->client->addTimer($time, function () {
                $this->remaining = -1;
                $this->startQueue();
            });
        }
        
        $this->manager->client->getLoop()->futureTick(function () {
            $this->process();
        });
    }
    
    protected function process() {
        try {
            $this->running = true;
            
            $item = \array_shift($this->queue);
            if($item) {
                $this->manager->client->writeLog('debug', 'ESI Queue processing item "'.$item['method'].' '.$item['url'].'"');
                $this->request($item['method'], $this->base.$item['url'], $item['data'], $item['files'])->then(function ($response) use ($item) {
                    $this->manager->client->writeLog('debug', 'Got response for ESI Queue processing item "'.$item['method'].' '.$item['url'].'"');
                    
                    $this->remaining = (int) $response->getHeader('x-esi-error-limit-remain')[0];
                    $this->reset = (int) $response->getHeader('x-esi-error-limit-reset')[0];
                    if ($response->getStatusCode() == 200) {
                        $data = \json_decode((string) $response->getBody(), true);
                        $item['resolve']($data);
                    } else {
                        $item['reject'](new \Exception($data['data']['error']));
                    }
                    $this->running = false;
                    $this->startQueue();
                }, function ($error) use ($item) {
                    $item['reject']($error);
                    $this->running = false;
                    $this->startQueue();
                });
            }
            
            $this->running = false;
        } catch(\Exception $e) {
            $this->client->errorConsole($e);
            $this->running = false;
        }
    }
    
    protected function request(string $method, string $url, $data = null, array $files = array()) {
        return \React\Promise\resolve()->then(function () use ($method, $url, $data, $files) {
            $request = new \GuzzleHttp\Psr7\Request($method, $url, array(
//              'Authorization' => 'Client-ID '.$this->key,
                'User-Agent' => \CharlotteDunois\Yasmin\Utils\URLHelpers::DEFAULT_USER_AGENT
            ));
            
            $options = array(
                'http_errors' => false,
                'protocols' => array('https'),
                'expect' => false,
                'proxy' => 'socks5h://127.0.0.1:9050'
            );
            
            if(!empty($data)) {
                $options['json'] = $data;
            }
            
            if(!empty($files)) {
                $options['multipart'] = array();
                foreach($files as $file) {
                    $options['multipart'][] = array(
                        'name' => 'image',
                        'contents' => $file['data'],
                        'filename' => $file['name']
                    );
                }
            }
            
            return \CharlotteDunois\Yasmin\Utils\URLHelpers::makeRequest($request, $options);
        });
    }
}
