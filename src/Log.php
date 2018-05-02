<?php
/**
 * Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

namespace CharlotteDunois\Bots\Jibril;

class Log {
    private static $logfileLevels = array('debug' => 4, 'info' => 3, 'warn' => 2, 'error' => 1, 'none' => 0);
    private static $logfileLevelReporting = 4;
    private static $logfilePath = null;
    private static $logfileDebugPath = null;
    private static $logfileSizeLimit = 1048576; // 1MB
    
    private function __construct() {
        
    }
    
    private function __destruct() {
        
    }
    
    /**
     * Sets the logfile.
     * @param string  $path
     */
    static function setLogFile(string $path) {
        self::$logfilePath = $path;
    }
    
    /**
     * Sets the debug logfile.
     * @param string  $path
     */
    static function setDebugLogFile(string $path) {
        self::$logfileDebugPath = $path;
    }
    
    /**
     * Sets the logfile size limit (in KB).
     * @param int  $size
     */
    static function setLogfileSizeLimit(int $size) {
        self::$logfileSizeLimit = $size * 1024;
    }
    
    /**
     * Sets or gets the reporting level.
     * @param string|int|null  $level
     * @return bool|int
     */
    static function reporting($level = null) {
        if(empty($level) && $level !== 0) {
            return self::$logfileLevelReporting;
        }
        
        if(\is_int($level)) {
            if(\in_array($level, self::$logfileLevels)) {
                self::$logfileLevelReporting = $level;
                return true;
            }
        } else {
            $level = \strtolower((string) $level);
            if(isset(self::$logfileLevels[$level])) {
                self::$logfileLevelReporting = self::$logfileLevels[$level];
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Formats a log message.
     * @return string
     */
    static function format(string $level, string $message) {
        $microtime = \explode(' ', \microtime(false));
        $microtime = \substr($microtime[0], 2, 3);
        return \date('d.m.Y, H:i:s').'.'.$microtime.' - ['.$level.']: '.$message;
    }
    
    /**
     * Writes a message to the appropriate logfile.
     * @param string|int  $level
     * @param string      $message
     * @return bool
     */
    static function write($level, $message) {
        if(self::$logfileLevelReporting === 0) {
            return true;
        }
        
        if(empty(self::$logfilePath)) {
            self::$logfilePath = IN_DIR.'/logs/logfile.txt';
        }
        
        if(empty(self::$logfileDebugPath)) {
            self::$logfileDebugPath = IN_DIR.'/logs/logfile_debug.txt';
        }
        
        if(empty($level) || empty($message)) {
            return false;
        }
        
        if(\is_int($level)) {
            $level = \array_search($level, self::$logfileLevels, true);
        }
        
        $level = \strtolower((string) $level);
        if(!\array_key_exists($level, self::$logfileLevels)) {
            $level = 'error';
        }
        
        if($level === 'none' || self::$logfileLevels[$level] > self::$logfileLevelReporting) {
            return true;
        }
        
        if($level === 'debug') {
            $path = self::$logfileDebugPath;
        } else {
            $path = self::$logfilePath;
        }
        
        if(\file_exists($path) && \filesize($path) >= self::$logfileSizeLimit) {
            $newPath = \explode('.', $path);
            $ext = \array_pop($newPath);
            
            $newPath = \implode('.', $newPath);
            $pattern = $newPath.'_*.'.$ext;
            
            $files = \count(\glob($pattern));
            \rename($path, $newPath.'_'.($files + 1).'.'.$ext);
        }
        
        return @\file_put_contents($path, self::format($level, $message).\PHP_EOL, \FILE_APPEND);
    }
}
