<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/8/5 0005
 * Time: 17:52
 */
class PHPServer_Master {
    /**
     * @var PHPServer_Worker[]
     */
    protected $workers = array();

    protected $gotTerm = false;

    /**
     * @var PHPServer_Signal
     */
    protected $signal;

    protected $pidFile;

    protected function signalChildHandler() {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            $this->onChildExit($pid, $status);
        }
        gc_collect_cycles();
    }
    protected function signalTermHandler() {
        $this->gotTerm = true;
        foreach ($this->workers as $worker) {
            posix_kill($worker->getPid(), SIGTERM);
        }
    }

    public function __construct() {
        cli_set_process_title($GLOBALS['argv'][0].':master');

        $_this = $this;

        $this->signal = new PHPServer_Signal;
        $this->signal->registerHandler(SIGCHLD, function() use ($_this) {
            PHPServer_Helper::callProtectedMethod($_this, 'signalChildHandler');
        });
        $this->signal->registerHandler(SIGTERM, function() use ($_this) {
            PHPServer_Helper::callProtectedMethod($_this, 'signalTermHandler');
        });
        $this->signal->registerHandler(SIGINT, function() use ($_this) {
            PHPServer_Helper::callProtectedMethod($_this, 'signalTermHandler');
        });

        $this->pidFile = $GLOBALS['argv'][0].'.pid';
    }

    public function setPidFile($filename) {
        $this->pidFile = $filename;
        return $this;
    }

    protected function onChildExit($pid, $status) {
        $workerId = -1;
        foreach ($this->workers as $k=>$worker) {
            if ($pid == $worker->getPid()) {
                $workerId = $k;
                break;
            }
        }
        if ($workerId == -1) {
            throw new RuntimeException('unknown child pid');
        }

        if ($this->gotTerm) {
            $this->workers[$workerId]->callFromMasterOnTerm($status);
            unset($this->workers[$workerId]);
            return;
        }

        if (pcntl_wifexited($status) && 0==pcntl_wexitstatus($status)) {
            $this->workers[$workerId]->callFromMasterOnSuccess($status);
            unset($this->workers[$workerId]);
            return;
        }

        $this->workers[$workerId]->callFromMasterOnError($status);

        $this->internalSpawnWorker($this->workers[$workerId]);
    }

    public static function internalFork() {
        $pid = pcntl_fork();
        switch ($pid) {
            case -1:
                throw new RuntimeException('error on call pcntl_fork()');
            default: // parent
                return $pid;
        }
    }

    public function demonize() {
        $this->signal->registerHandler(SIGTTOU, function(){});
        $this->signal->registerHandler(SIGTTIN, function(){});
        $this->signal->registerHandler(SIGTSTP, function(){});
        $this->signal->registerHandler(SIGHUP, function(){});
        if (static::internalFork()) {
            exit(0);
        } else {
            posix_setsid();
            if (static::internalFork()) {
                exit(0);
            }
            umask(0);
            PHPServer_Helper::writePidFile($this->pidFile);
        }
    }

    public function spawnWorker(PHPServer_Worker $worker) {
        if (in_array($worker, $this->workers)) {
            throw new Exception('u can not spawn a worker instance twice');
        }
        PHPServer_Helper::setProtectedProperty($worker, 'id', sizeof($this->workers));
        $this->workers[$worker->getId()] = $worker;
        $this->internalSpawnWorker($worker);
    }

    protected function internalSpawnWorker(PHPServer_Worker $worker) {
        $pid = static::internalFork();
        PHPServer_Helper::setProtectedProperty($worker, 'pid', $pid);
        if ($pid) { // parent
            return $this;
        } else { // child
            PHPServer_Helper::callProtectedMethod($worker, 'setupSignalHandler');
            cli_set_process_title($GLOBALS['argv'][0].':worker');
            $worker->job();
            exit(0);
        }
    }

    public function waitWorkers() {
        while (sizeof($this->workers)>0) {
            $this->signal->dispatch();
            usleep(100000);
        }
    }
}