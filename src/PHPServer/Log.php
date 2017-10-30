<?php



interface PHPServer_ILog {
    static function logFile();
}

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-30 00030
 * Time: 11:02
 */
abstract class PHPServer_Log implements PHPServer_ILog{

    const LEVEL_EMERG = 'EMERG';
    const LEVEL_ALERT = 'ALERT';
    const LEVEL_CRIT = 'CRIT';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_NOTICE = 'NOTICE';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';

    public static function format($msg, $level, $context = array()) {
        $msg = '['.date('Y-m-d H:i:s').'] '.$level.' '.$msg;
        if (!empty($context)) {
            $msg .= ' '.json_encode($context);
        }
        return $msg;
    }

    protected static function log($msg, $level, $context = array()) {
        $msg = self::format($msg, $level, $context);
        file_put_contents(self::logFile(), $msg.PHP_EOL, FILE_APPEND);
    }

    public static function debug($msg, $context = array()) {
        self::log($msg, self::LEVEL_DEBUG, $context);
    }
    public static function info($msg, $context = array()) {
        self::log($msg, self::LEVEL_INFO, $context);
    }
    public static function notice($msg, $context = array()) {
        self::log($msg, self::LEVEL_NOTICE, $context);
    }
    public static function warning($msg, $context = array()) {
        self::log($msg, self::LEVEL_WARNING, $context);
    }
    public static function error($msg, $context = array()) {
        self::log($msg, self::LEVEL_ERROR, $context);
    }
    public static function crit($msg, $context = array()) {
        self::log($msg, self::LEVEL_CRIT, $context);
    }
    public static function alert($msg, $context = array()) {
        self::log($msg, self::LEVEL_ALERT, $context);
    }
    public static function emerg($msg, $context = array()) {
        self::log($msg, self::LEVEL_EMERG, $context);
    }

    public static function aLine($s) {
        return str_replace(array("\r","\n","\t","\0","\x0B"), array('\r','\n','\t','\0','\x0B'), $s);
    }
}