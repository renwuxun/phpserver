<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
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
                    die("{$argv[0]} is running\n");
                }
                break;
            case 'stop';
                if (!self::ifServerRunning($pidFile)) {
                    die("{$argv[0]} is not running\n");
                }
                posix_kill((int)file_get_contents($pidFile), SIGTERM);
                die();
                break;
            default:
                echo "usage:\n";
                echo "      php {$argv[0]}\n";
                echo "      php {$argv[0]} daemon\n";
                echo "      php {$argv[0]} stop\n";
                die();
        }
    }

    public static function setProtectedProperty($obj, $property, $val) {
        $clsname = get_class($obj);
        do {
            $reflectCls = new ReflectionClass($clsname);
            if ($reflectCls->hasProperty($property)) {
                break;
            } else {
                $clsname = get_parent_class($clsname);
            }
        } while ($clsname);

        $pro = $reflectCls->getProperty($property);
        if ($pro->isPrivate() || $pro->isProtected()) {
            $pro->setAccessible(true);
        }
        $pro->setValue($obj, $val);
    }

    public static function callProtectedMethod($obj, $method, $args = null) {
        $clsname = get_class($obj);
        do {
            $reflectCls = new ReflectionClass($clsname);
            if ($reflectCls->hasMethod($method)) {
                break;
            } else {
                $clsname = get_parent_class($clsname);
            }
        } while ($clsname);

        $mtd = $reflectCls->getMethod($method);
        if ($mtd->isPrivate() || $mtd->isProtected()) {
            $mtd->setAccessible(true);
        }
        $mtd->invoke($obj, $args);
    }
}