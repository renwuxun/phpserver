<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/8/8 0008
 * Time: 10:29
 */
class PHPServer_Helper {

    public static function ifServerRunning($pidFile) {
        if (file_exists($pidFile)) {
            $pid = (int)file_get_contents($pidFile);
            if (posix_kill($pid, 0)) {
                if (shell_exec("cat /proc/$pid/cmdline") == shell_exec("cat /proc/".posix_getpid()."/cmdline")) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function writePidFile($pidFile) {
        $path = substr($pidFile, 0, strrpos($pidFile, '/'));
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        if (file_put_contents($pidFile, posix_getpid())) {
            register_shutdown_function(function() use($pidFile) {
                if (file_exists($pidFile) && posix_getpid() == (int)file_get_contents($pidFile)) {
                    unlink($pidFile);
                }
            });
            return true;
        }
        return false;
    }

    public static function checkContinue($cmd, $pidFile) {
        global $argv;
        switch ($cmd) {
            case 'start':
            case 'daemon':
                if (self::ifServerRunning($pidFile)) {
                    fprintf(STDERR, "{$argv[0]} is running\n");
                    exit(1);
                }
                break;
            case 'stop';
                if (!self::ifServerRunning($pidFile)) {
                    fprintf(STDERR, "{$argv[0]} is not running\n");
                    exit(1);
                }
                posix_kill((int)file_get_contents($pidFile), SIGTERM);
                exit(0);
                break;
            case 'reload';
                if (!self::ifServerRunning($pidFile)) {
                    fprintf(STDERR, "{$argv[0]} is not running\n");
                    exit(1);
                }
                posix_kill((int)file_get_contents($pidFile), SIGHUP);
                exit(0);
                break;
            default:
                echo "usage:\n";
                echo "      php {$argv[0]}\n";
                echo "      php {$argv[0]} daemon\n";
                echo "      php {$argv[0]} stop\n";
                echo "      php {$argv[0]} reload\n";
                exit(0);
        }
    }

    /**
     * @return string
     */
    public static function localIP() {
        static $localIP = null;
        if (!$localIP) {
            $localIP = trim(shell_exec("ifconfig |grep 'inet addr:'|awk '{print $2;exit}'|awk -F: '{print $2}'"));
        }
        return $localIP;
    }

    public static function noPharFrefix($path) {
        if ('phar://' == substr($path, 0, 7)) {
            return substr($path, 7);
        }
        return $path;
    }
}
