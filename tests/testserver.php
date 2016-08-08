<?php
/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/8/5 0005
 * Time: 17:56
 */


set_time_limit(0);


require __DIR__.'/autoload.php';


$cmd = isset($argv[1]) ? $argv[1] : 'start';

PHPServer_Helper::checkContinue($cmd, $argv[0].'.pid');


class MyWorker extends PHPServer_Worker{
    public function job() {
        cli_set_process_title($GLOBALS['argv'][0].':worker');
        while (!$this->ifShouldExit()) {
            $this->getSignal()->dispatch();
            echo posix_getpid().' say hi'.PHP_EOL;
            sleep(1);
        }
        exit(0);
    }
    public function callFromMasterOnError($status) {
        echo 'worker exit on err'.PHP_EOL;
    }
    public function callFromMasterOnSuccess($status) {
        echo 'worker exit on suc'.PHP_EOL;
    }
    public function callFromMasterOnTerm($status) {
        echo 'worker exit on term'.PHP_EOL;
    }
}


$master = new PHPServer_Master;

if ($cmd == 'daemon') {
    $master->demonize();
}

$master->spawnWorker(new MyWorker);
$master->spawnWorker(new MyWorker);

cli_set_process_title($GLOBALS['argv'][0].':master');

$master->onMasterExit(function(){
    echo 'master exit'.PHP_EOL;
});

$master->waitWorkers();