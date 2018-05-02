<?php
/**
 * Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

namespace CharlotteDunois\Bots\Jibril;

class JibrilClient extends \CharlotteDunois\Livia\LiviaClient {
    /** @var \CharlotteDunois\Bots\Jibril\APIManager */
    public $apimanager;
    
    /** @var \CharlotteDunois\Athena\AthenaCache */
    public $cache;
    
    /** @var \React\Promise\Promise */
    public $initialReady;
    
    /** @var int */
    public $startup;
    
    protected $config = array();
    protected $configPath = 'config.json';
    protected $commandsCalled = 0;
    protected $initialCommandsCalled = 0;
    
    /** @var \CharlotteDunois\Yasmin\Utils\Collection */
    protected $eventListeners;
    /** @var \CharlotteDunois\Collect\Set */
    protected $eventInhibitors;
    /** @var \CharlotteDunois\Yasmin\Utils\Collection */
    protected $moduleIntervals;
    /** @var \CharlotteDunois\Yasmin\Utils\Collection */
    protected $moduleTimeouts;
    
    protected $shutdown = false;
    protected $shutdownInit = false;
    
    /**
     * JibrilClient constructor.
     * @param array                                $options
     * @param \React\EventLoop\LoopInterface|null  $loop
     * @throws \Exception
     */
    function __construct(array $options = array(), ?\React\EventLoop\LoopInterface $loop = null) {
        parent::__construct($options, $loop);
        
        $this->setErrorHandler();
        \register_shutdown_function(function () {
            $this->writeLog('warn', 'Shutdown initiated');
            
            $this->saveConfig();
            
            $promise = new \GuzzleHttp\Promise\Promise();
            $this->saveCommandsCalled()->then(array($promise, 'resolve'), array($promise, 'reject'))->done(null, array($this, 'handlePromiseRejection'));
            
            try {
                $promise->wait();
            } catch(\Throwable | \Exception | \Error $e) {
                /* Continue regardless of error */
            }
        });
        
        $this->startup = \time();
        $this->configPath = $options['configPath'] ?? $this->configPath;
        
        if(\file_exists($this->configPath)) {
            $this->config = \json_decode(\file_get_contents($this->configPath), true);
        }
        
        $this->initialReady = (new \React\Promise\Promise(function (callable $resolve) {
            $this->once('ready', $resolve);
        }));
        
        $this->addPeriodicTimer(120, function() {
            $this->saveConfig(false);
            $this->saveCommandsCalled();
        });
        
        $this->addPeriodicTimer(1800, function () {
            $this->writeLog('info', 'Starting user cleanup');
            
            $amount = 0;
            foreach($this->users as $user) {
                if(empty($user->prevOffline) || ($user->prevOffline && $user->presence && $user->presence->status !== 'offline')) {
                    $user->prevOffline = false;
                }
                
                if(!$user->prevOffline && ($user->presence === null || $user->presence->status === 'offline')) {
                    $user->prevOffline = true;
                }
                
                if(!$this->isOwner($user) && !$user->userFetched && $user->prevOffline && ($user->presence === null || $user->presence->status === 'offline')) {
                    $amount++;
                    $this->users->delete($user->id);
                    unset($user);
                }
            }
            
            \gc_collect_cycles();
            $memory = \gc_mem_caches();
            
            $this->writeLog('info', 'Cleaned up users, removed '.$amount.' users and freed '.\CharlotteDunois\Bots\Jibril\Utils::formatBytes($memory));
        });
        
        $this->cache = new \CharlotteDunois\Athena\AthenaCache($this->getLoop());
        $this->cache->on('error', function ($error) {
            $this->writeLog('error', $error);
        });
        $this->cache->on('debug', function ($debug) {
            $this->writeLog('debug', $debug);
        });
        
        $this->dispatcher->addInhibitor(function (\CharlotteDunois\Livia\CommandMessage $message) {
            if($this->shutdown === true) {
                return array('Shutting down...', null);
            } elseif($this->shutdownInit === true && $this->isOwner($message->message->author) === false) {
                return array('Preparing to shutdown...', null);
            }
            
            return false;
        });
        
        $this->eventListeners = new \CharlotteDunois\Yasmin\Utils\Collection();
        $this->eventInhibitors = new \CharlotteDunois\Collect\Set();
        $this->moduleIntervals = new \CharlotteDunois\Yasmin\Utils\Collection();
        $this->moduleTimeouts = new \CharlotteDunois\Yasmin\Utils\Collection();
        
        $this->on('debug', function ($debug) {
            $this->debugConsole($debug);
        });
        $this->on('error', function ($error) {
            $this->errorConsole($error);
        });
        
        $this->on('commandRun', function ($command, $promise, $message) {
            $this->writeLog('info', 'Running command '.$command->name.' triggered by '.$message->message->author->tag.' ('.$message->message->author->id.')'.($message->message->guild ? ' in server '.$message->message->guild->id : ' in DM'));
            $this->commandsCalled++;
        });
        $this->on('commandBlocked', function ($message, $reason) {
            $this->writeLog('info', 'Command '.($message->command ? $message->command->groupID.':'.$message->command->groupID : '').' blocked; User '.$message->message->author->tag.' ('.$message->message->author->id.'): '.$reason);
        });
        $this->on('commandError', function ($command, $error) {
            $this->writeLog('error', 'Error in command '.$command->groupID.':'.$command->name, $error);
        });
        
        $this->apimanager = new \CharlotteDunois\Bots\Jibril\APIManager($this);
        $this->apimanager->initialize('ESI');
        
        $mysql = $this->getConfig('mysql');
        $db = new \React\MySQL\Connection($this->getLoop(), array(
            'host' => $mysql['host'],
            'user' => $mysql['user'],
            'passwd' => $mysql['password'],
            'dbname' => $mysql['db'],
            'port' => ($mysql['port'] ?? 3306)
        ));
        
        $this->setProvider((new \CharlotteDunois\Livia\Providers\MySQLProvider($db)))->then(function () {
            $this->initialCommandsCalled = $this->provider->get('global', 'commandsCalled');
            $this->commandsCalled += $this->initialCommandsCalled;
        }, function ($error) {
            $this->writeLog('debug', $error);
            $this->writeLog('error', 'There has been an error while initializing the MySQL provider. Error: '.$error->getMessage());
        });
    }
    
    /**
     * @inheritDoc
     */
    function destroy(bool $destroyUtils = true) {
        return $this->apimanager->destroyAll()->then(function () {
            $this->cache->destroy();
            return parent::destroy(true);
        });
    }
    
    /**
     * @return \React\Promise\Promise
     */
    function sendSuccess(\CharlotteDunois\Livia\CommandMessage $message, $add = '') {
        if(!empty($add)) {
            $add = '. Additional Info: '.$add;
        }
        
        return $message->say(':white_check_mark: Operation successful'.$add)->otherwise(array($this, 'errorConsole'));
    }
    
    /**
     * @return \React\Promise\Promise
     */
    function sendSuccessEmbed(\CharlotteDunois\Livia\CommandMessage $message, $text) {
        $embed = (new \CharlotteDunois\Yasmin\Models\MessageEmbed())->setColor(0x61FF00)->setDescription(':white_check_mark: '.$text);
        return $message->say('', array('embed' => $embed))->otherwise(array($this, 'errorConsole'));
    }
    
    /**
     * @return \React\Promise\Promise
     */
    function sendFailure(\CharlotteDunois\Livia\CommandMessage $message, $add = '') {
        if(!empty($add)) {
            $add = '. Additional Info: '.$add;
        }
        
        return $message->say(':x: Operation failed'.$add)->otherwise(array($this, 'errorConsole'));
    }
    
    /**
     * @return \React\Promise\Promise
     */
    function sendFailureEmbed(\CharlotteDunois\Livia\CommandMessage $message, $text) {
        $embed = (new \CharlotteDunois\Yasmin\Models\MessageEmbed())->setColor(0xFF0000)->setDescription(':x: '.$text);
        return $message->say('', array('embed' => $embed))->otherwise(array($this, 'errorConsole'));
    }
    
    /**
     * @return int
     */
    function getCommandsCalled() {
        return $this->commandsCalled;
    }
    
    /**
     * @return \React\Promise\PromiseInterface
     */
    function saveCommandsCalled() {
        if($this->provider === null) {
            return \React\Promise\resolve();
        }
        
        return $this->provider->set('global', 'commandsCalled', $this->commandsCalled);
    }
    
    /**
     * @return $this
     */
    function setShutdown(bool $shutdown) {
        $this->shutdown = $shutdown;
        return $this;
    }
    
    /**
     * @return $this
     */
    function setInitShutdown(bool $shutdown) {
        $this->shutdownInit = $shutdown;
        return $this;
    }
    
    function setOption($name, $value) {
        $this->options[$name] = $value;
    }
    
    /**
     * @return array
     * @throws \Exception
     */
    function loadConfig(bool $intoMemory = true) {
        if(!\file_exists($this->configPath)) {
            throw new \Exception('Config file does not exist');
        }
        
        $config = \json_decode(\file_get_contents($this->configPath), true);
        
        if($intoMemory) {
            $this->config = $config;
        }
        
        return $config;
    }
    
    /**
     * @return mixed
     */
    function getConfig(string $key = null, $defVal = null) {
        if($key === null) {
            return $this->config;
        }
        
        if(\array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        
        return $defVal;
    }
    
    /**
     * @return mixed[]
     */
    function getConfigByKeys(array $keys, $defVal = null) {
        $config = array();
        
        foreach($keys as $key) {
            $config[$key] = $this->getConfig($key, $defVal);
        }
        
        return $config;
    }
    
    /**
     * @return bool
     * @throws \Exception
     */
    function updateConfig(string $key, $value) {
        $this->config[$key] = $value;
        return $this->saveConfig(false);
    }
    
    /**
     * @return bool
     * @throws \Exception
     */
    function saveConfig(bool $throw = true) {
        $this->writeLog('debug', 'Saving config...');
        
        $code = \file_put_contents($this->configPath, \json_encode($this->config));
        if($code === false) {
            if($throw) {
                throw new \Exception('Unable to write to config file');
            }
            
            $this->writeLog('error', 'Unable to write to config file');
            return false;
        }
        
        return true;
    }
    
    /**
     * @param string|string[]  $event
     * @return array<string, int>
     */
    function addEventListener($event, callable $fn, array $options = array()) {
        $backtrace = \debug_backtrace(null, 2);
        $backtrace = \explode('/', \str_replace('\\', '/', \array_pop($backtrace)['file']));
        $backtrace = \substr(\array_pop($backtrace), 0, -4);
        
        if(!\is_array($event)) {
            if(!\is_string($event)) {
                throw new \InvalidArgumentException('Event must be a string or an array of strings');
            }
            
            $event = array($event);
        }
        
        $sizes = array();
        foreach($event as $ev) {
            $eventlist = $this->eventListeners->get($ev);
            if($eventlist === null) {
                $eventlist = new \CharlotteDunois\Yasmin\Utils\Collection();
                $eventlist->set('internal', (new \CharlotteDunois\Yasmin\Utils\Collection()));
                $eventlist->set('modules', (new \CharlotteDunois\Yasmin\Utils\Collection()));
                
                $this->eventListeners->set($ev, $eventlist);
            }
            
            $obj = new \stdClass();
            $obj->fn = $fn;
            $obj->once = (bool) ($options['once'] ?? false);
            
            if(\stripos(__FILE__, $backtrace) !== false) {
                $hold = $eventlist->get('internal');
                $size = $hold->count();
                
                $hold->set($size, $obj);
            } else {
                $hold = $eventlist->get('modules');
                $list = $hold->get($backtrace);
                if($list === null) {
                    $list = new \stdClass();
                    $list->dateline = \time();
                    $list->filename = $backtrace;
                    $list->ignore = (bool) ($options['ignore'] ?? false);
                    $list->listeners = new \CharlotteDunois\Yasmin\Utils\Collection();
                    
                    $hold->set($backtrace, $list);
                }
                
                $size = $list->listeners->count();
                $list->listeners->set($size, $obj);
            }
            
            $sizes[$ev] = $size;
        }
        
        return $sizes;
    }
    
    /**
     * @return bool
     */
    function removeEventListener(string $event, int $position) {
        $eventlist = $this->eventListeners->get($event);
        if($eventlist === null) {
            return true;
        }
        
        $backtrace = \debug_backtrace(null, 2);
        $backtrace = \explode('/', \str_replace('\\', '/', \array_pop($backtrace)['file']));
        $backtrace = \substr(\array_pop($backtrace), 0, -4);
        
        if(\stripos(__FILE__, $backtrace) !== false) {
            $hold = $eventlist->get('internal');
            $hold->set($position, null);
        } else {
            $hold = $eventlist->get('modules');
            $hold = $hold->get($backtrace);
            if($hold === null) {
                return true;
            }
            
            $hold['listeners']->set($position, null);
        }
        
        return true;
    }
    
    /**
     * @return bool
     */
    function removeEventListenersByFilename(string $filename) {
        $filename = \explode('/', \str_replace('\\', '/', $filename));
        $filename = \str_replace('.php', '', \array_pop($filename));
        
        foreach($this->eventListeners as $eventlist) {
            $hold = $eventlist->get('modules');
            $hold->delete($filename);
        }
        
        return true;
    }
    
    /**
     * @param string $event
     * @param array  ...$args
     * @return array
     */
    function callEventListeners(string $event, ...$args) {
        $response = null;
        
        $eventcol = $this->eventListeners->get($event);
        if($eventcol !== null) {
            $list = $eventcol->get('internal');
            foreach($list as $index => $fn) {
                if(!\is_array($args)) {
                    $args = array($args);
                }
                
                if(!($fn instanceof \stdClass)) {
                    continue;
                }
                
                $fnc = $fn->fn;
                if(!\is_callable($fnc)) {
                    continue;
                }
                
                try {
                    $response = $fnc($event, ...$args);
                    if($response !== null && !($response instanceof \React\Promise\PromiseInterface)) {
                        $payload = $response;
                    }
                    
                    if($fn->once) {
                        $list->set($index, null);
                    }
                } catch(\Throwable | \Exception | \ErrorException $e) {
                    $this->writeLog('error', 'Error while trying to call event listener "internal-'.$index.'" for event "'.$event.'"', $e);
                }
            }
            
            $list2 = $eventcol->get('modules');
            foreach($list2 as $filename => $file) {
                if(!($file instanceof \stdClass)) {
                    continue;
                }
                
                if($file->ignore === false) {
                    try {
                        if($this->registry->resolveCommand($filename)->isEnabledIn(null) === false) {
                            continue;
                        }
                        
                        if(!\is_array($args)) {
                            $args = array($args);
                        }
                        
                        foreach($this->eventInhibitors as $inh) {
                            $rt = $inh($event, ...$args);
                            if($rt === false) {
                                continue;
                            }
                        }
                    } catch(\Throwable | \Exception $e) {
                        continue;
                    }
                }
                
                foreach($file->listeners as $index => $fn) {
                    if(!\is_array($args)) {
                        $args = array($args);
                    }
                    
                    if(!($fn instanceof \stdClass)) {
                        continue;
                    }
                    
                    $fnc = $fn->fn;
                    if(!\is_callable($fnc)) {
                        continue;
                    }
                    
                    try {
                        $response = $fnc($event, ...$args);
                        if($response !== null && !($response instanceof \React\Promise\PromiseInterface)) {
                            $payload = $response;
                        }
                        
                        if($fn->once) {
                            $file->listeners->set($index, null);
                        }
                    } catch(\Throwable | \Exception | \ErrorException $e) {
                        $this->writeLog('error', 'Error while trying to call event listener "'.$filename.'-'.$index.'" for event "'.$event.'"', $e);
                    }
                }
            }
        }
        
        return $args;
    }
    
    /**
     * @return $this
     */
    function addEventInhibitor(callable $inhibitor) {
        $this->eventInhibitors->add($inhibitor);
        return $this;
    }
    
    /**
     * @return $this
     */
    function removeEventInhibitor(callable $inhibitor) {
        $this->eventInhibitors->delete($inhibitor);
        return $this;
    }
    
    /**
     * @param int|float  $interval
     * @return \React\EventLoop\Timer\TimerInterface
     */
    function setInterval($interval, callable $fn) {
        $backtrace = \debug_backtrace(null, 2);
        $backtrace = \explode('/', \str_replace('\\', '/', \array_pop($backtrace)['file']));
        $backtrace = \substr(\array_pop($backtrace), 0, -4);
        
        if($this->moduleIntervals->has($backtrace) === false) {
            $this->moduleIntervals->set($backtrace, (new \CharlotteDunois\Collect\Set()));
        }
        
        $tm = $this->addPeriodicTimer($interval, function () use ($backtrace, $fn) {
            try {
                $fn();
            } catch(\Throwable | \Exception | \ErrorException $e) {
                $this->writeLog('error', 'An error ocurred when trying to run a module interval created by "'.$backtrace.'"');
            }
        });
        
        $set = $this->moduleIntervals->get($backtrace);
        $set->add($tm);
        
        return $tm;
    }
    
    function clearInterval(\React\EventLoop\Timer\TimerInterface $timer) {
        $backtrace = \debug_backtrace(null, 2);
        $backtrace = \explode('/', \str_replace('\\', '/', \array_pop($backtrace)['file']));
        $backtrace = \substr(\array_pop($backtrace), 0, -4);
        
        $set = $this->moduleIntervals->get($backtrace);
        if($set !== null) {
            $set->delete($timer);
        }
        
        $timer->cancel();
    }
    
    /**
     * @param string $name
     */
    function clearIntervalsByName(string $name) {
        $set = $this->moduleIntervals->get($name);
        if($set !== null) {
            foreach($set as $timer) {
                $timer->cancel();
            }
            
            $set->clear();
        }
    }
    
    /**
     * @param int|float  $timeout
     * @return \React\EventLoop\Timer\TimerInterface
     */
    function setTimeout($timeout, callable $fn) {
        $backtrace = \debug_backtrace(null, 2);
        $backtrace = \explode('/', \str_replace('\\', '/', \array_pop($backtrace)['file']));
        $backtrace = \substr(\array_pop($backtrace), 0, -4);
        
        if($this->moduleTimeouts->has($backtrace) === false) {
            $this->moduleTimeouts->set($backtrace, (new \CharlotteDunois\Collect\Set()));
        }
        
        $set = $this->moduleTimeouts->get($backtrace);
        $tm = $this->addTimer($timeout, function () use ($backtrace, $fn, &$set, &$tm) {
            try {
                $fn();
            } catch(\Throwable | \Exception | \ErrorException $e) {
                $this->writeLog('error', 'An error occurred when trying to run a module timeout created by "'.$backtrace.'"');
            }
            
            $set->delete($tm);
        });
        
        $set->add($tm);
        return $tm;
    }
    
    function clearTimeout(\React\EventLoop\Timer\TimerInterface $timer) {
        $backtrace = \debug_backtrace(null, 2);
        $backtrace = \explode('/', \str_replace('\\', '/', \array_pop($backtrace)['file']));
        $backtrace = \substr(\array_pop($backtrace), 0, -4);
        
        $set = $this->moduleTimeouts->get($backtrace);
        if($set !== null) {
            $set->delete($timer);
        }
        
        $timer->cancel();
    }
    
    function clearTimeoutsByName(string $name) {
        $set = $this->moduleTimeouts->get($name);
        if($set !== null) {
            foreach($set as $timer) {
                $timer->cancel();
            }
            
            $set->clear();
        }
    }
    
    function writeLog(string $level, $str, ...$args) {
        $str = (string) $str;
        $log = $str.(!empty($args) ? \PHP_EOL.\implode(\PHP_EOL, $args) : '');
        Log::write($level, $log);
        
        if($level === 'error') {
            \fwrite(\STDERR, Log::format($level, $log).\PHP_EOL);
        } elseif($level !== 'debug' || $this->getConfig('debug') === true) {
            echo Log::format($level, $log).\PHP_EOL;
        }
    }
    
    /**
     * @param \Throwable|\Exception|\Error  $error
     * @return \React\Promise\RejectedPromise
     */
    function errorConsole($error) {
        $this->debugConsole($error);
        $this->writeLog('error', $error->getMessage());
        
        return \React\Promise\reject($error);
    }
    
    function debugConsole($str, ...$args) {
        if($this->getConfig('debug') !== false) {
            $this->writeLog('debug', $str, ...$args);
        }
    }
    
    function emit($name, ...$args) {
        $arg = parent::emit($name, ...$args);
        
        if(!\in_array($name, array('debug', 'error', 'warn'))) {
            $this->callEventListeners($name, ...$args);
        }
        
        return $arg;
    }
    
    function setErrorHandler() {
        \set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if(\error_reporting() === 0 || !(\error_reporting() & $errno)) {
                return true;
            }
            
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }
}
