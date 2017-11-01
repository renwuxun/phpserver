<?php

/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/8/8 0008
 * Time: 10:29
 */
class PHPServer_Helper {

    public static function ifServerRunning($pidFile) {
        return file_exists($pidFile) && posix_kill((int)file_get_contents($pidFile), 0);
    }

    public static function writePidFile($pidFile) {
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
                    fprintf(STDERR, PHPServer_Log::format("{$argv[0]} is running\n", PHPServer_Log::LEVEL_WARNING));
                    exit(1);
                }
                break;
            case 'stop';
                if (!self::ifServerRunning($pidFile)) {
                    fprintf(STDERR, PHPServer_Log::format("{$argv[0]} is not running\n", PHPServer_Log::LEVEL_WARNING));
                    exit(1);
                }
                posix_kill((int)file_get_contents($pidFile), SIGTERM);
                exit(0);
                break;
            case 'reload';
                if (!self::ifServerRunning($pidFile)) {
                    fprintf(STDERR, PHPServer_Log::format("{$argv[0]} is not running\n", PHPServer_Log::LEVEL_WARNING));
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
                die();
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
}