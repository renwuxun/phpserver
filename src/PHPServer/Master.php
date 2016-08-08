<?php

/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/8/5 0005
 * Time: 17:52
 */
class PHPServer_Master {
    /**
     * @var PHPServer_Worker[]
     */
    protected $workers = [];

    protected $gotTerm = false;

    /**
     * @var PHPServer_Signal
     */
    protected $signal;

    protected $masterExitCallback;

    protected $pidFile;
    protected $stdoutFile;
    protected $stderrFile;

    public function __construct() {
        $this->signal = new PHPServer_Signal;
        $this->signal->registerHandler(SIGCHLD, function(){
            while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
                $this->onChildExit($pid, $status);
            }
            gc_collect_cycles();
        });
        $this->signal->registerHandler(SIGTERM, function(){
            $this->gotTerm = true;
            foreach ($this->workers as $worker) {
                posix_kill($worker->getPid(), SIGTERM);
            }
        });

        $this->pidFile = $GLOBALS['argv'][0].'.pid';
    }

    public function setPidFile($filename) {
        $this->pidFile = $filename;
        return $this;
    }

    public function setStdoutFile($filename) {
        $this->stdoutFile = $filename;
        return $this;
    }

    public function setStderrFile($filename) {
        $this->stderrFile = $filename;
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
            fclose(STDIN);
            $GLOBALS['stdin'] = fopen('/dev/null', 'r');
            if ($this->stdoutFile) {
                fclose(STDOUT);
                $GLOBALS['stdout'] = fopen($this->stdoutFile, 'a');
            }
            if ($this->stderrFile) {
                fclose(STDERR);
                $GLOBALS['stderr'] = fopen($this->stderrFile, 'a');
            }
            PHPServer_Helper::writePidFile($this->pidFile);
        }
    }

    public function spawnWorker(PHPServer_Worker $worker) {
        if (in_array($worker, $this->workers)) {
            throw new Exception('u can not spawn a worker instance twice');
        }
        static::setProtectedProperty($worker, 'id', sizeof($this->workers));
        $this->internalSpawnWorker($worker);
    }

    protected function internalSpawnWorker(PHPServer_Worker $worker) {
        $pid = static::internalFork();
        static::setProtectedProperty($worker, 'pid', $pid);
        if ($pid) { // parent
            $this->workers[$worker->getId()] = $worker;
            return $this;
        } else { // child
            static::callProtectedMethod($worker, 'setupSignalHandler');
            $worker->job();
            exit(0);
        }
    }

    public function waitWorkers() {
        while (sizeof($this->workers)>0) {
            $this->signal->dispatch();
            usleep(100000);
        }

        if (is_callable($this->masterExitCallback)) {
            call_user_func_array($this->masterExitCallback, [$this]);
        }
    }

    public function onMasterExit(callable $callback) {
        $this->masterExitCallback = $callback;
    }

    protected static function setProtectedProperty($obj, $property, $val) {
        $clsname = get_class($obj);
        do {
            $reflectCls = new ReflectionClass($clsname);
            if ($reflectCls->hasProperty($property)) {
                break;
            } else {
                $clsname = get_parent_class($clsname);
            }
        } while ($clsname);

        $pro = $reflectCls->getProperty($property);
        if ($pro->isPrivate() || $pro->isProtected()) {
            $pro->setAccessible(true);
        }
        $pro->setValue($obj, $val);
    }

    protected static function callProtectedMethod($obj, $method, $args = null) {
        $clsname = get_class($obj);
        do {
            $reflectCls = new ReflectionClass($clsname);
            if ($reflectCls->hasMethod($method)) {
                break;
            } else {
                $clsname = get_parent_class($clsname);
            }
        } while ($clsname);

        $mtd = $reflectCls->getMethod($method);
        if ($mtd->isPrivate() || $mtd->isProtected()) {
            $mtd->setAccessible(true);
        }
        $mtd->invoke($obj, $args);
    }
}