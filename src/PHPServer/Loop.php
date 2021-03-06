<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-24 00024
 * Time: 15:39
 */
class PHPServer_Loop {

    protected $break = false;

    protected $now;

    protected $loopTimeout = 2.0;

    /**
     * @var callable[]
     */
    protected $idleHandlers = array();

    /**
     * @var SplQueue
     */
    protected $onceQueue;

    /**
     * @var SplQueue
     */
    protected $timeoutQueue;
    /**
     * @var PHPServer_TimerHeap
     */
    protected $timerHeap;

    /**
     * @var SplQueue
     */
    protected $writeQueue;
    /**
     * @var array
     */
    protected $writeHandlers = array();

    /**
     * @var SplQueue
     */
    protected $readQueue;
    /**
     * @var array
     */
    protected $readHandlers = array();

    /**
     * @var SplQueue
     */
    protected $signalQueue;
    /**
     * @var array
     */
    protected $signalHandlers = array();


    protected $listenSignals = array();

    protected $errno = 0;

    public function __construct() {
        $this->signalQueue = new SplQueue;
        $this->signalQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
        $this->timeoutQueue = new SplQueue;
        $this->timeoutQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
        $this->readQueue = new SplQueue;
        $this->readQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
        $this->writeQueue = new SplQueue;
        $this->writeQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
        $this->onceQueue = new SplQueue;
        $this->onceQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);

        $this->timerHeap = new PHPServer_TimerHeap;
    }

    protected function process() {
        $this->now = microtime(true);

        $loopTimeout = $this->loopTimeout;

        $hasHandlerToCall = false;

        // process once
        if (!$this->onceQueue->isEmpty()) {
            $hasHandlerToCall = true;
        }
        // process timeout
        while (!$this->timerHeap->isEmpty() && $this->timerHeap->top()->fireAt <= $this->now) {
            $hasHandlerToCall = true;
            $this->timeoutQueue->enqueue($this->timerHeap->extract());
        }

        if ($hasHandlerToCall) {// 有事情可干了
            $loopTimeout = 0.0;
        } else {
            if (!$this->timerHeap->isEmpty()) { // 最近要发生的事件是什么时候
                $delta = $this->timerHeap->top()->fireAt - $this->now;
                if ($delta < $loopTimeout) {
                    $loopTimeout = sprintf("%.4f", $delta);
                }
            }
        }

        //echo posix_getpid().' readHandlers count: '.sizeof($this->readHandlers).' , readHandlers count: '.sizeof($this->writeHandlers).PHP_EOL;
        if (!empty($this->readHandlers) || !empty($this->writeHandlers)) {
            $readFps = array();
            foreach ($this->readHandlers as $readHandler) {
                $readFps[] = $readHandler[0];
            }
            $writeFps = array();
            foreach ($this->writeHandlers as $writeHandler) {
                $writeFps[] = $writeHandler[0];
            }
            //echo posix_getpid().' '.sizeof($readFps).'r,w'.sizeof($writeFps)." timeout:{$loopTimeout} ".(($loopTimeout-(int)($loopTimeout))*1000000).PHP_EOL;
            $except = array();
            if (0 < @stream_select($readFps, $writeFps, $except, (int)$loopTimeout, ($loopTimeout-(int)($loopTimeout))*1000000)) {
                foreach ($readFps as $readFp) {
                    //echo posix_getpid().' 有可读事件发生'.PHP_EOL;
                    $this->readQueue->enqueue((int)$readFp);
                }
                foreach ($writeFps as $writeFp) {
                    //echo posix_getpid().' 有可写事件发生'.PHP_EOL;
                    $this->writeQueue->enqueue((int)$writeFp);
                }

                if (!empty($readFps) || !empty($writeFps)) { // 有事可干了
                    $loopTimeout = 0.0;
                }
            }
        }

        $sigInfo = array();
        while (pcntl_sigtimedwait($this->listenSignals, $sigInfo, (int)$loopTimeout, ($loopTimeout-(int)($loopTimeout))*1000000000) > 0) {
            $this->signalQueue->enqueue($sigInfo['signo']);
            $loopTimeout = 0.0;
        }
    }

    protected function dispatch() {
        $idle = true;
        // 暂时一个事件只挂一个处理句柄
        foreach ($this->signalQueue as $signo) {
            $idle = false;
            call_user_func($this->signalHandlers[$signo], $signo);
        }
        foreach ($this->timeoutQueue as $timer) {
            $idle = false;
            /**
             * @var $timer PHPServer_Timer
             */
            call_user_func($timer->handler);
        }
        foreach ($this->readQueue as $iFp) {
            $idle = false;
            //echo posix_getpid().' 触发读事件'.PHP_EOL;
            call_user_func($this->readHandlers[$iFp][1], $this->readHandlers[$iFp][0]);
        }
        foreach ($this->writeQueue as $iFp) {
            $idle = false;
            //echo posix_getpid().' 触发写事件'.PHP_EOL;
            call_user_func($this->writeHandlers[$iFp][1], $this->writeHandlers[$iFp][0]);
        }
        foreach ($this->onceQueue as $once) {
            $idle = false;
            /**
             * @var $once callable
             */
            call_user_func($once);
        }

        // 跑空转事件
        if ($idle) {
            foreach ($this->idleHandlers as $idleHandler) {
                call_user_func($idleHandler);
            }
        }
    }

    /**
     * @param $idleHandler callable()
     */
    public function registerIdleHandler($idleHandler) {
        $this->idleHandlers[] = $idleHandler;
    }

    /**
     * @param $onceHandler callable()
     */
    public function registerOnceHandler($onceHandler) {
        $this->onceQueue->enqueue($onceHandler);
    }

    public function registerTimerHandler(PHPServer_Timer $timer) {
        $this->timerHeap->insert($timer);
    }
    public function removeTimerHandler(PHPServer_Timer $timer) {
        $newHeap = new PHPServer_TimerHeap;
        foreach ($this->timerHeap as $_timer) {
            if ($_timer != $timer) {
                $newHeap->insert($_timer);
            }
        }
        unset($this->timerHeap);
        $this->timerHeap = $newHeap;
    }

    public function registerReadHandler($fp, $readHandler) {
        $this->readHandlers[(int)$fp] = array($fp, $readHandler);
    }
    public function removeReadHandler($fp) {
        unset($this->readHandlers[(int)$fp]);
    }

    public function registerWriteHandler($fp, $writeHandler) {
        $this->writeHandlers[(int)$fp] = array($fp, $writeHandler);
    }
    public function removeWriteHandler($fp) {
        unset($this->writeHandlers[(int)$fp]);
    }

    public function registerSignalHandler($signo, $signalHandler) {
        $this->signalHandlers[$signo] = $signalHandler;
        $this->listenSignals = array_keys($this->signalHandlers);
    }
    public function removeSignalHandler($signo) {
        unset($this->signalHandlers[$signo]);
        $this->listenSignals = array_keys($this->signalHandlers);
    }

    public function run() {
        self::blockSignal($this->listenSignals);

        do {
            $this->process();
            $this->dispatch();
            if (
                empty($this->signalHandlers) &&
                empty($this->readHandlers) &&
                empty($this->writeHandlers) &&
                $this->timerHeap->isEmpty() &&
                $this->onceQueue->isEmpty() &&
                empty($this->idleHandlers)
            ) {
                break;
            }
        } while (!$this->break);

        return $this->errno;
    }

    public function setBreak($bool) {
        $this->break = $bool;
    }

    public static function blockSignal($signals) {
        pcntl_sigprocmask(SIG_BLOCK, $signals);
    }

    public static function unblockSignal($signals) {
        pcntl_sigprocmask(SIG_UNBLOCK, $signals);
    }
}