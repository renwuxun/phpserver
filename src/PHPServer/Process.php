<?php


/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-12 00012
 * Time: 11:58
 */
abstract class PHPServer_Process {

    /**
     * @var PHPServer_Loop
     */
    protected $eventLoop;

    protected $defaultSignalHandlers = array(
        array(SIGCHLD, 'chldHandler'),
        array(SIGTERM, 'termHandler'),
        array(SIGINT, 'intHandler'),
        array(SIGQUIT, 'quitHandler'),
        array(SIGHUP, 'hupHandler'),
        array(SIGWINCH, 'winchHandler'),
        array(SIGUSR1, 'usr1Handler'),
        array(SIGUSR2, 'usr2Handler'),
        array(SIGIO, 'ioHandler')
    );

    public function __construct(PHPServer_Loop $eventLoop = null) {
        if ($eventLoop) {
            $this->eventLoop = $eventLoop;
        } else {
            $this->eventLoop = new PHPServer_Loop;
        }

        foreach ($this->defaultSignalHandlers as $item) {
            if ($item[1] != '') {
                $this->eventLoop->registerSignalHandler($item[0], array($this, $item[1]));
            }
        }

        pcntl_signal(SIGALRM, array($this, 'alrmHandler'));
    }

    /**
     * @internal
     */
    public function chldHandler() {}
    /**
     * @internal
     */
    public function termHandler() {}
    /**
     * @internal
     */
    public function intHandler() {}
    /**
     * @internal
     */
    public function quitHandler() { // 立即退出
        exit(SIGQUIT);
    }
    /**
     * @internal
     */
    public function hupHandler() {}
    /**
     * @internal
     */
    public function winchHandler() { // 优雅退出
        $this->eventLoop->setBreak(true);
    }
    /**
     * @internal
     */
    public function usr1Handler() {}
    /**
     * @internal
     */
    public function usr2Handler() {}
    /**
     * @internal
     */
    public function alrmHandler() {}
    /**
     * @internal
     */
    public function ioHandler() {}

    public function run() {
        return $this->eventLoop->run();
    }

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
                fprintf(STDOUT, 'error on call pcntl_fork()');
                exit(1);
            default: // parent
                return $pid;
        }
    }

    /**
     * @param callable $childPrcess
     * @return int
     */
    public static function spawn($childPrcess) {
        $childPid = self::fork();
        if ($childPid) { // parent
            return $childPid;
        } else { // child
            $childPrcess();
            exit(0);
        }
    }
}