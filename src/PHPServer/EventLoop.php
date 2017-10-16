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

    public function __construct() {
        $this->signalQueue = new SplQueue;
        $this->signalQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
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
        if (empty($this->idleHandlers) && empty($this->onceHandlers)) {
            $signalTimeoutSec = $this->signalTimeoutSec;
            $signalTimeoutNanoSec = $this->signalTimeoutNanoSec;
        }

        // process signal
        $sigInfo = array();
        if (pcntl_sigtimedwait($this->listenSignals, $sigInfo, $signalTimeoutSec, $signalTimeoutNanoSec)>0) {
            $this->signalQueue->enqueue($sigInfo['signo']);
        }
    }

    protected function dispatchEvent() {
        // dispatch idle handlers
        if (0==$this->signalQueue->count() && empty($this->onceHandlers)) { // 空转状态
            if (!empty($this->idleHandlers)) {
                foreach ($this->idleHandlers as $idleHandler) {
                    call_user_func($idleHandler);
                }
            }
        } else { // 有事件发生
            // dispatch signal handlers
            if (!empty($this->signalHandlers)) { // 有信号处理器
                foreach ($this->signalQueue as $signo) {
                    if (!empty($this->signalHandlers[$signo])) {  // 若某信号有信号处理器
                        foreach ($this->signalHandlers[$signo] as $signalHandler) {
                            call_user_func($signalHandler, $signo);
                        }
                    }
                }
            }
            if (!empty($this->onceHandlers)) { // 处理一锤子买卖
                foreach ($this->onceHandlers as $k=>$onceHandler) {
                    unset($this->onceHandlers[$k]);
                    call_user_func($onceHandler);
                }
            }
        }
    }

    public function loop() {
        $this->beforeLoop();
        do {
            $this->processEvent();
            $this->dispatchEvent();

            if (empty($this->signalHandlers) && empty($this->idleHandlers) && empty($this->onceHandlers)) {
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
//    public function unregisterSignalHandler($signal, $handler) {
//        if (!empty($this->signalHandlers[$signal])) {
//            foreach ($this->signalHandlers[$signal] as $k=>$signalHandler) {
//                if ($signalHandler == $handler) {
//                    unset($this->signalHandlers[$signal][$k]);
//                    break;
//                }
//            }
//        }
//    }

    public function registerIdleHandler($handler) {
        $this->idleHandlers[] = $handler;
    }
//    public function unregisterIdleHandler($handler) {
//        foreach ($this->idleHandlers as $k=>$idleHandler) {
//            if ($handler == $idleHandler) {
//                unset($this->idleHandlers[$k]);
//                break;
//            }
//        }
//    }

    public function registerOnceHandler($handler) {
        $this->onceHandlers[] = $handler;
    }
//    public function unregisterOnceHandler($handler) {
//        foreach ($this->onceHandlers as $k=>$onceHandlers) {
//            if ($handler == $onceHandlers) {
//                unset($this->onceHandlers[$k]);
//                break;
//            }
//        }
//    }

    public function setSignalWaitTimeout($signalTimeoutSec = 0, $signalTimeoutNanoSec = 0) {
        $this->signalTimeoutSec = $signalTimeoutSec;
        $this->signalTimeoutNanoSec = $signalTimeoutNanoSec;
    }
}