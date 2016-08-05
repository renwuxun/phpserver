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
    private $signal;
    /**
     * @var bool
     */
    private $gotTerm = false;

    /**
     * PHPServer_Worker constructor.
     */
    public function __construct() {
        $this->signal = new PHPServer_Signal;
    }

    private function setupSignalHandler() {
        $this->signal->registerHandler(SIGTERM, function(){
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

    /**
     * @return bool
     */
    public function ifShouldExit() {
        return $this->gotTerm;
    }

    /**
     * @return PHPServer_Signal
     */
    public function getSignal() {
        return $this->signal;
    }

    abstract public function job();
    abstract public function callFromMasterOnError($status);
    abstract public function callFromMasterOnSuccess($status);
    abstract public function callFromMasterOnTerm($status);

}