<?php

/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/8/5 0005
 * Time: 17:53
 */
abstract class PHPServer_Worker {
    /**
     * 不同任务的worker应该有不同的id
     * @var int
     */
    private $id;
    /**
     * @var int
     */
    private $pid;

    /**
     * @var PHPServer_Signal
     */
    private $_signal;
    /**
     * @var bool
     */
    private $gotTerm = false;


    private function setupSignalHandler() {
        $this->getSignal()->registerHandler(SIGTERM, function(){
            $this->gotTerm = true;
        });
    }

    /**
     * @return int
     */
    public function getPid() {
        return $this->pid;
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    public function inWorkerContext() {
        return 0===$this->pid;
    }

    /**
     * @return bool
     * @throws Exception
     */
    protected function ifShouldExit() {
        if (!$this->inWorkerContext()) { // is not in worker process context
            throw new Exception('u can not call '.__FUNCTION__.' from out of worker process context');
        }
        return $this->gotTerm;
    }

    /**
     * @return PHPServer_Signal
     * @throws Exception
     */
    protected function getSignal() {
        if (!$this->inWorkerContext()) { // is not in worker process context
            throw new Exception('u can not call '.__FUNCTION__.' from out of worker process context');
        }
        if (null === $this->_signal) {
            $this->_signal = new PHPServer_Signal;
        }
        return $this->_signal;
    }

    abstract public function job();
    abstract public function callFromMasterOnError($status);
    abstract public function callFromMasterOnSuccess($status);
    abstract public function callFromMasterOnTerm($status);

}