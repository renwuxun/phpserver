<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-12 00012
 * Time: 17:57
 */
class PHPServer_EventLoop {

    protected $errno = 0;

    protected $break = false;

    protected $listenSignals = array();

    /**
     * @var SplQueue
     */
    protected $signalQueue;
    protected $signalHandlers = array();

    protected $idleHandlers = array();

    /**
     * @var array
     */
    protected $onceHandlers = array();

    protected $signalTimeoutSec = 1;
    protected $signalTimeoutNanoSec = 0;

    /**
     * @var SplQueue
     */
    protected $writeFpsQueue;
    /**
     * @var SplQueue
     */
    protected $readFpsQueue;
    protected $writeFpsHandlers = array();
    protected $readFpsHandlers = array();

    public function __construct() {
        $this->signalQueue = new SplQueue;
        $this->signalQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
        $this->writeFpsQueue = new SplQueue;
        $this->writeFpsQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
        $this->readFpsQueue = new SplQueue;
        $this->readFpsQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
    }

    public static function blockSignal($signals) {
        pcntl_sigprocmask(SIG_BLOCK, $signals);
    }

    public static function unblockSignal($signals) {
        pcntl_sigprocmask(SIG_UNBLOCK, $signals);
    }

    protected function beforeLoop() {
        $this->listenSignals = array_keys($this->signalHandlers);
        self::blockSignal($this->listenSignals);
    }

    protected function afterLoop() {}

    protected function processEvent() {
        $signalTimeoutSec = 0;
        $signalTimeoutNanoSec = 0;
        if (
            empty($this->idleHandlers)
            && empty($this->onceHandlers)
        ) {
            $signalTimeoutSec = $this->signalTimeoutSec;
            $signalTimeoutNanoSec = $this->signalTimeoutNanoSec;
        }

        //echo posix_getpid().' '.date('Y-m-d H:i:s').' '.'网络超时时间:',$signalTimeoutSec,'秒,',$signalTimeoutNanoSec.'纳秒'.PHP_EOL;

        // process socket io
        $readFps = empty($this->readFpsHandlers) ? array() : array_column($this->readFpsHandlers, 'fp');
        $writeFps = empty($this->writeFpsHandlers) ? array() : array_column($this->writeFpsHandlers, 'fp');
        $fps = array();
        //echo posix_getpid().' '.date('Y-m-d H:i:s').' '.'要不要进入stream_select:',(int)(!empty($readFps) || !empty($writeFps)).PHP_EOL;
        if (!empty($readFps) || !empty($writeFps)) {
            //echo posix_getpid().' '.date('Y-m-d H:i:s').' '.'进入stream_select [$readFps:'.sizeof($readFps).',$writeFps:'.sizeof($writeFps),']'.PHP_EOL;
            if (0 < @stream_select($readFps, $writeFps, $fps, $signalTimeoutSec, $signalTimeoutNanoSec/1000)) {
                //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."stream_select有事件发生".PHP_EOL;
                foreach ($readFps as $readFp) {
                    $this->readFpsQueue->enqueue((int)$readFp);
                }
                foreach ($writeFps as $writeFp) {
                    $this->writeFpsQueue->enqueue((int)$writeFp);
                }
            } else {
                //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."stream_select无事件发生".PHP_EOL;
            }
            $signalTimeoutSec = 0;
            $signalTimeoutNanoSec = 0;
        }

        // process signal
        $sigInfo = array();
        //echo posix_getpid().' '.date('Y-m-d H:i:s').' '.'信号超时计算结果:',$signalTimeoutSec,'秒,',$signalTimeoutNanoSec.'纳秒'.PHP_EOL;
        while (pcntl_sigtimedwait($this->listenSignals, $sigInfo, $signalTimeoutSec, $signalTimeoutNanoSec) > 0) {
            $this->signalQueue->enqueue($sigInfo['signo']);
            $signalTimeoutSec = 0;
            $signalTimeoutNanoSec = 0;
        }
    }

    protected function dispatchEvent() {
        // dispatch idle handlers
        if (
            0==$this->signalQueue->count()
            && empty($this->onceHandlers)
            && 0==$this->readFpsQueue->count()
            && 0==$this->writeFpsQueue->count()
        ) { // 空转状态
            //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."处理空转事件开始".PHP_EOL;
            if (!empty($this->idleHandlers)) {
                foreach ($this->idleHandlers as $idleHandler) {
                    call_user_func($idleHandler);
                }
            }
            //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."处理空转事件结束".PHP_EOL;
        } else { // 有事件发生
            //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."处理非空转事件开始".PHP_EOL;
            // dispatch signal handlers
            if (!empty($this->signalHandlers)) { // 有信号处理器
                foreach ($this->signalQueue as $signo) {
                    if (!empty($this->signalHandlers[$signo])) {  // 若某信号有信号处理器
                        foreach ($this->signalHandlers[$signo] as $signalHandler) {
                            call_user_func($signalHandler, $signo);
                            //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."处理信号事件".PHP_EOL;
                        }
                    }
                }
            }
            if (!empty($this->onceHandlers)) { // 处理一锤子买卖
                foreach ($this->onceHandlers as $k => $onceHandler) {
                    call_user_func($onceHandler);
                    unset($this->onceHandlers[$k]);
                    //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."处理一锤子买卖".PHP_EOL;
                }
            }
            if (0 < $this->readFpsQueue->count()) {
                foreach ($this->readFpsQueue as $fp) {
                    if (isset($this->readFpsHandlers[$fp]['handler']) && is_callable($this->readFpsHandlers[$fp]['handler'])) {
                        call_user_func($this->readFpsHandlers[$fp]['handler'], $this->readFpsHandlers[$fp]['fp']);
                        //echo posix_getpid().' '.date('Y-m-d H:i:s').' '.'处理网络读事件'.PHP_EOL;
                    }
                }
            }
            if (0 < $this->writeFpsQueue->count()) {
                foreach ($this->writeFpsQueue as $fp) {
                    if (isset($this->writeFpsHandlers[$fp]['handler']) && is_callable($this->writeFpsHandlers[$fp]['handler'])) {
                        call_user_func($this->writeFpsHandlers[$fp]['handler'], $this->writeFpsHandlers[$fp]['fp']);
                        //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."处理网络写事件".PHP_EOL;
                    }
                }
            }
            //echo posix_getpid().' '.date('Y-m-d H:i:s').' '."处理非空转事件结束".PHP_EOL;
        }
    }

    public function loop() {
        $this->beforeLoop();
        do {
            $this->processEvent();
            $this->dispatchEvent();

            if (empty($this->signalHandlers)
                && empty($this->idleHandlers)
                && empty($this->onceHandlers)
                && empty($this->readFpsHandlers)
                && empty($this->writeFpsHandlers)
            ) {
                break;
            }
        } while (!$this->break);
        $this->afterLoop();

        return $this->errno;
    }

    public function setBreak($bool) {
        $this->break = $bool;
    }

    public function registerSignalHandler($signal, $handler) {
        if (!isset($this->signalHandlers[$signal])) {
            $this->signalHandlers[$signal] = array();
        }
        $this->signalHandlers[$signal][] = $handler;
    }

    public function registerIdleHandler($handler) {
        $this->idleHandlers[] = $handler;
    }

    public function registerOnceHandler($handler) {
        $this->onceHandlers[] = $handler;
    }

    public function registerFpsReadHandler($fp, $readHandler) {
        if (null == $readHandler) {
            unset($this->readFpsHandlers[(int)$fp]);
            return;
        }
        $this->readFpsHandlers[(int)$fp]['handler'] = $readHandler;
        $this->readFpsHandlers[(int)$fp]['fp'] = $fp;
    }

    public function registerFpsWriteHandler($fp, $writeHandler) {
        if (null == $writeHandler) {
            unset($this->writeFpsHandlers[(int)$fp]);
            return;
        }
        $this->writeFpsHandlers[(int)$fp]['handler'] = $writeHandler;
        $this->writeFpsHandlers[(int)$fp]['fp'] = $fp;
    }

    public function setSignalWaitTimeout($signalTimeoutSec = 0, $signalTimeoutNanoSec = 0) {
        $this->signalTimeoutSec = $signalTimeoutSec;
        $this->signalTimeoutNanoSec = $signalTimeoutNanoSec;
    }
}