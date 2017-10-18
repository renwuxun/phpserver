<?php
/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/8/5 0005
 * Time: 17:56
 */


set_time_limit(0);


require __DIR__.'/autoload.php';


class MyWorker extends PHPServer_Worker{

    public function __construct() {
        parent::__construct();

        $this->eventLoop->registerIdleHandler(
            function(){ echo 'worker idle'.PHP_EOL; usleep(1000*100);}
        );
        $this->eventLoop->registerSignalHandler(
            SIGQUIT,
            function(){
                echo 'worker '.posix_getpid().' got SIGQUIT:'.SIGQUIT.PHP_EOL;
                exit(SIGQUIT);
            }
        );
        $loop = $this->eventLoop;
        $this->eventLoop->registerSignalHandler(
            SIGWINCH,
            function() use ($loop) {
                echo 'worker '.posix_getpid().' got SIGWINCH:'.SIGQUIT.PHP_EOL;
                $loop->setBreak(true);
            }
        );
    }
}


$master = new PHPServer_Master(array('MyWorker', 'MyWorker'));

$master->run();

echo 'master exit'.PHP_EOL;