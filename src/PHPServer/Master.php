<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/8/5 0005
 * Time: 17:52
 */
class PHPServer_Master extends PHPServer_Process {

    /**
     * @var array
     */
    protected $workers = array();

    protected $gotExitSignal = false;

    protected $pidFile;

    public function __construct(array $workerNames) {

        parent::__construct();

        cli_set_process_title($GLOBALS['argv'][0].':master');

        if (!$this->pidFile) {
            $this->pidFile = $GLOBALS['argv'][0].'.pid';
        }


        $cmd = isset($GLOBALS['argv'][1]) ? $GLOBALS['argv'][1] : 'start';
        PHPServer_Helper::checkContinue($cmd, $this->pidFile);
        if ($cmd == 'daemon') {
            $this->demonize();
        }

        foreach ($workerNames as $workerName) {
            $this->spawnWorker($workerName);
        }

        PHPServer_Helper::writePidFile($this->pidFile);
    }

    public function chldHandler() {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            $workName = $this->workers[$pid];
            unset($this->workers[$pid]);
            if ($this->gotExitSignal) { // master got term|int|quit
                if (empty($this->workers)) {
                    $this->eventLoop->setBreak(true);
                }
                fprintf(STDOUT, "worker[$pid] exit\n");
                continue;
            }


            $this->spawnWorker($workName);
        }

        gc_collect_cycles();
    }
    public function termHandler() {
        $this->gotExitSignal = true;
        foreach ($this->workers as $pid=>$workerName) {
            posix_kill($pid, SIGQUIT);
            posix_kill($pid, SIGALRM);
        }
    }
    public function intHandler() {
        $this->gotExitSignal = true;
        foreach ($this->workers as $pid=>$workerName) {
            posix_kill($pid, SIGQUIT);
            posix_kill($pid, SIGALRM);
        }
    }
    public function quitHandler() {
        $this->gotExitSignal = true;
        foreach ($this->workers as $pid=>$workerName) {
            posix_kill($pid, SIGWINCH);
            posix_kill($pid, SIGALRM);
        }
    }
    public function hupHandler() { // reload
        foreach ($this->workers as $pid=>$workerName) {
            posix_kill($pid, SIGWINCH);
            posix_kill($pid, SIGALRM);
        }
    }
    public function winchHandler() {}

    public function spawnWorker($workerName) {
        if (!is_subclass_of($workerName, 'PHPServer_Worker') ) {
            throw new Exception('worker must subclass of PHPServer_Worker', 1);
        }

        $childPid = static::spawn(
            function() use ($workerName){
                /**
                 * @var $worker PHPServer_Worker
                 */
                $worker = new $workerName;
                $worker->run();
            }
        );

        $this->workers[$childPid] = $workerName;
    }
}