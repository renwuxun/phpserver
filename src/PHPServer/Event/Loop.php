<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-24 00024
 * Time: 15:39
 */
class PHPServer_Loop {

    protected $break = 0;

    protected $now;

    protected $loopTimeout = 1.0;

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
     * @var PHPServer_Event_TimerHeap
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

        $this->timerHeap = new PHPServer_Event_TimerHeap;
    }

    protected function process() {
        $this->now = microtime(true);

        $loopTimeout = $this->loopTimeout;

        $hasHandlerToCall = false;

        // process idle
        // process once
        // process timeout
        while (!$this->timerHeap->isEmpty() && $this->timerHeap->top()->fireAt <= $this->now) {
            $hasHandlerToCall = true;
            $this->timeoutQueue->enqueue($this->timerHeap->extract());
        }

        if (!$hasHandlerToCall) {
            if (!empty($this->idleHandlers) || !$this->onceQueue->isEmpty()) {
                $hasHandlerToCall = true;
            }
        } else {
            if (!$this->timerHeap->isEmpty()) {
                $delta = $this->timerHeap->top()->fireAt - $this->now;
                if ($delta < $loopTimeout) {
                    $loopTimeout = sprintf("%.4f", $delta);
                }
            }
        }

        if (!empty($this->readFps) || !empty($this->writeFps)) {
            if ($hasHandlerToCall) {
                $loopTimeout = 0.0;
            }

            $readFps = array_column($this->readHandlers, 'fp');
            $writeFps = array_column($this->writeHandlers, 'fp');
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
            }

            if ($loopTimeout > 0) {
                $this->now += $loopTimeout;
            }

            $loopTimeout = 0.0;
        }

        if ($loopTimeout > 0) { // 说明没经历过网络i/o环节，信号环节需要等待
            $this->now += $loopTimeout;
        }

        $sigInfo = array();
        while (pcntl_sigtimedwait($this->listenSignals, $sigInfo, (int)$loopTimeout, ($loopTimeout-(int)($loopTimeout))*1000000000) > 0) {
            $this->signalQueue->enqueue($sigInfo['signo']);
            $loopTimeout = 0.0;
        }
    }

    protected function dispatch() {
        // 暂时一个事件只挂一个处理句柄
        foreach ($this->signalQueue as $signo) {
            /**
             * @var $signo int
             */
            call_user_func($this->signalHandlers[$signo], $signo);
        }
        foreach ($this->timeoutQueue as $timer) {
            /**
             * @var $timer PHPServer_Event_Timer
             */
            call_user_func($timer->handler);
        }
        foreach ($this->readQueue as $iFp) {
            /**
             * @var $iFp int
             */
            //echo posix_getpid().' 触发读事件'.PHP_EOL;
            call_user_func($this->readHandlers[$iFp]['handler'], $this->readHandlers[$iFp]['fp']);
        }
        foreach ($this->writeQueue as $iFp) {
            //echo posix_getpid().' 触发写事件'.PHP_EOL;
            call_user_func($this->writeHandlers[$iFp]['handler'], $this->writeHandlers[$iFp]['fp']);
        }
        foreach ($this->onceQueue as $once) {
            /**
             * @var $once callable
             */
            call_user_func($once);
        }
        foreach ($this->idleHandlers as $idleHandler) {
            call_user_func($idleHandler);
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

    public function registerTimerHandler(PHPServer_Event_Timer $timer) {
        $this->timerHeap->insert($timer);
    }
    public function removeTimerHandler(PHPServer_Event_Timer $timer) {
        $newHeap = new PHPServer_Event_TimerHeap;
        foreach ($this->timerHeap as $_timer) {
            if ($_timer != $timer) {
                $newHeap->insert($_timer);
            }
        }
        unset($this->timerHeap);
        $this->timerHeap = $newHeap;
    }

    public function registerReadHandler($fp, $readHandler) {
        $this->readHandlers[(int)$fp] = array(
            'fp'=>$fp,
            'handler'=>$readHandler
        );
    }
    public function removeReadHandler($fp) {
        unset($this->readHandlers[(int)$fp]);
    }

    public function registerWriteHandler($fp, $writeHandler) {
        $this->writeHandlers[(int)$fp] = array(
            'fp'=>$fp,
            'handler'=>$writeHandler
        );
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