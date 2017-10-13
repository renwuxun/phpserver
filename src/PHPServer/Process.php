<?php


/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-12 00012
 * Time: 11:58
 */
abstract class PHPServer_Process {

    public static function demonize() {
        if (static::fork()) {
            exit(0);
        } else {
            posix_setsid();
            if (static::fork()) {
                exit(0);
            }
            umask(0);
        }
    }

    private static function fork() {
        $pid = pcntl_fork();
        switch ($pid) {
            case -1:
                throw new RuntimeException('error on call pcntl_fork()');
            default: // parent
                return $pid;
        }
    }

    public static function spawn(callable $childPrcess) {
        $childPid = self::fork();
        if ($childPid) { // parent
            return $childPid;
        } else { // child
            $childPrcess();
            exit(0);
        }
    }
}