<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/8/5 0005
 * Time: 17:54
 */
class PHPServer_Signal implements Countable {
    /**
     * @var []
     */
    private $handlers;

    /**
     * @var SplQueue
     */
    private $signalQueue;

    public function __construct() {
        $this->handlers = array();

        $this->signalQueue = new SplQueue();
        $this->signalQueue->setIteratorMode(SplQueue::IT_MODE_DELETE);
    }

    /**
     * Register given callable with pcntl signal.
     *
     * @param int $signal The pcntl signal.
     * @param callback $handler The signal handler.
     *
     * @return $this
     * @throws RuntimeException If could not register handler with pcntl_signal.
     */
    public function registerHandler($signal, $handler) {
        if (!is_callable($handler)) {
            throw new InvalidArgumentException('The handler is not callable');
        }

        if (!isset($this->handlers[$signal])) {
            $this->handlers[$signal] = array();

            if (!pcntl_signal($signal, array($this, 'handleSignal'))) {
                throw new RuntimeException(sprintf('Could not register signal %d with pcntl_signal', $signal));
            };
        };

        $this->handlers[$signal][] = $handler;
        return $this;
    }

    /**
     * Return true is ant handler registered with given signal.
     *
     * @param int $signal The pcntl signal.
     *
     * @return bool
     */
    public function hasHandler($signal) {
        return !empty($this->handlers[$signal]);
    }

    /**
     * Enqueue pcntl signal to dispatch in feature.
     *
     * @internal
     *
     * @param int $signal The pcntl signal.
     *
     * @return void
     */
    public function handleSignal($signal) {
        $this->signalQueue->enqueue($signal);
    }

    /**
     * Execute `pcntl_signal_dispatch` and process all registered handlers.
     *
     * @return $this
     */
    public function dispatch() {
        pcntl_signal_dispatch();

        foreach ($this->signalQueue as $signal) {
            foreach ($this->handlers[$signal] as &$callable) {
                call_user_func($callable, $signal);
            }
        }

        return $this;
    }

    /**
     * Return count of queued signals.
     *
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count() {
        return count($this->signalQueue);
    }
}