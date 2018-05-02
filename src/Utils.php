<?php
/**
 * Jibril
 * Copyright 2018 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
*/

namespace CharlotteDunois\Bots\Jibril;

class Utils {
    /**
     * @return string
     */
    static function formatDateTime(\DateTime $dt, bool $withHours = true) {
        return $dt->format('l, F jS Y'.($withHours ? ', H:i' : ''));
    }
    
    /**
     * @return string|null
     */
    static function getURL(string $text) {
         \preg_match('/((([A-Za-z]{3,9}:(?:\/\/)?)(?:[-;:&=\+\$,\w]+@)?[A-Za-z0-9.-]+|(?:www.|[-;:&=\+\$,\w]+@)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w\\-_]*)?\??(?:[-\+=&;%@.\w_]*)#?(?:[\w]*))?)/miu', $text, $matches);
         return ($matches[1] ?? null);
    }
    
    /**
     * @return string
     */
    static function getFilenameFromURL(string $url) {
        $url = \explode('/', $url);
        $filename = \explode('?', \array_pop($url))[0];
        return $filename;
    }
    
    /**
     * @return string
     */
    static function calculateTimeElapsed(string $s, $delim = null, bool $disableSkip = false) {
        if(!\is_string($delim) || \strlen($delim) === 0) {
            $delim = null;
        }
        
        $d = 0;
        $h = 0;
        $m = 0;
        
        if($s > 86399) {
            $d = \floor(($s / 86400));
            $s -= ($d * 86400);
        }
        if($s > 3559) {
            $h = \floor(($s / 3600));
            $s -= ($h * 3600);
        }
        if($s > 59) {
            $m = \floor(($s / 60));
            $s -= ($m * 60);
        }
        $s = \round($s);
        
        $time = array();
        if($d > 0) {
            if($delim) {
                $time[] = array($d, 'd');
            } else {
                $time[] = array($d, 'day'.($d !== 1 ? 's' : ''));
            }
        }
        
        if(($disableSkip === true && \count($time) > 0) || $h > 0) {
            if($delim) {
                $time[] = array($h, 'h');
            } else {
                $time[] = array($h, 'hour'.($h !== 1 ? 's' : ''));
            }
        }
        
        if(($disableSkip === true && \count($time) > 0) || $m > 0) {
            if($delim) {
                $time[] = array($m, 'm');
            } else {
                $time[] = array($m, 'minute'.($m !== 1 ? 's' : ''));
            }
        }
        
        if(($disableSkip === true && \count($time) > 0) || $s >= 0) {
            if($delim) {
                $time[] = array( $s, 's');
            } else {
                $time[] = array( $s, 'second'.($s !== 1 ? 's' : ''));
            }
        }
        
        $time = \array_filter($time, function ($t) use ($delim, $disableSkip) {
            return ($delim || $disableSkip === true || $t[0] !== 0);
        });
        
        if(\count($time) === 0 && s === 0) {
            if($delim) {
                $time[] = array($s, 's');
            } else {
                $time[] = array($s, 'second'.($s !== 1 ? 's' : ''));
            }
        }
        
        $unitSepKey = \count($time) - 2;
        
        $str = '';
        $unit = '';
        
        foreach($time as $k => $t) {
            if($delim) {
                if($k > 0 && $t[0] < 10) {
                    $t[0] = '0'.$t[0];
                }
                
                if(!$unit) {
                    $unit = $t[1];
                }
                
                $str .= $t[0].$delim;
            } else {
                $unit = ', ';
                if($unitSepKey === $k) {
                    $unit = ' and ';
                }
                
                $str .= $t[0].' '.$t[1].$unit;
            }
        }
        
        if($delim) {
            $str = \substr($str, 0, (\strlen($str) - 1)).$unit;
        } else {
            $str = \substr($str, 0, (\strlen($str) - 1));
        }
        
        return \rtrim($str, ', ');
    }
    
    /**
     * @return string
     */
    static function formatBytes(int $bytes, int $maxCounter = -1, bool $useIECunit = false) {
        $units = ($useIECunit ? array('B', 'KiB', 'MiB', 'GiB', 'TiB') : array('B', 'KB', 'MB', 'GB', 'TB' ));
        $unitlength = \count($units) - 1;
        
        $counter = 0;
        while(($bytes / 1024) >= 1 && ($maxCounter === -1 || (($maxCounter - 1) >= $counter)) && $counter < $unitlength) {
            $bytes = $bytes / 1024;
            $counter++;
        }
        
        return \ceil($bytes).' '.$units[$counter];
    }
}
