<?php

/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/8/5 0005
 * Time: 17:53
 */
abstract class PHPServer_Worker extends PHPServer_Process {

    /**
     * @var PHPServer_EventLoop
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
        array(SIGALRM, 'alrmHandler'),
        array(SIGIO, 'ioHandler')
    );

    public function __construct(PHPServer_EventLoop $eventLoop = null) {
        if ($eventLoop) {
            $this->eventLoop = $eventLoop;
        } else {
            $this->eventLoop = new PHPServer_EventLoop;
        }

        foreach ($this->defaultSignalHandlers as $item) {
            if ($item[1] != '') {
                $this->eventLoop->registerSignalHandler($item[0], array($this, $item[1]));
            }
        }
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
        return $this->eventLoop->loop();
    }
}