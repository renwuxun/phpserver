<?php



/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-30 00030
 * Time: 11:02
 */
class PHPServer_Log {

    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    public static function format($level, $msg, $context = array()) {
        $msg = date('Y/m/d H:i:s').' ['.$level.'] '.$msg;
        if (!empty($context)) {
            $msg .= ' '.json_encode($context);
        }
        return $msg;
    }

    public static function logPath() {
        static $logPath;
        if (!$logPath) {
            $logPath = dirname(dirname(dirname(dirname(dirname(__DIR__))))).'/log';
        }
        return $logPath;
    }

    public static function logFile() {
        return static::logPath().'/'.date('Y-m-d').'.log';
    }

    protected static function log($level, $msg, $context = array()) {
        $msg = static::format($level, $msg, $context);
        file_put_contents(static::logFile(), $msg.PHP_EOL, FILE_APPEND);
    }

    public static function debug($msg, $context = array()) {
        static::log(self::DEBUG, $msg, $context);
    }
    public static function info($msg, $context = array()) {
        static::log(self::INFO, $msg, $context);
    }
    public static function notice($msg, $context = array()) {
        static::log(self::NOTICE, $msg, $context);
    }
    public static function warning($msg, $context = array()) {
        static::log(self::WARNING, $msg, $context);
    }
    public static function error($msg, $context = array()) {
        static::log(self::ERROR, $msg, $context);
    }
    public static function critical($msg, $context = array()) {
        static::log(self::CRITICAL, $msg, $context);
    }
    public static function alert($msg, $context = array()) {
        static::log(self::ALERT, $msg, $context);
    }
    public static function emergency($msg, $context = array()) {
        static::log(self::EMERGENCY, $msg, $context);
    }

    public static function aLine($s) {
        return str_replace(array("\r","\n","\t","\0","\x0B"), array('\r','\n','\t','\0','\x0B'), $s);
    }
}