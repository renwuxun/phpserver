<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/8/5 0005
 * Time: 17:53
 */
abstract class PHPServer_Worker extends PHPServer_Process {

    public function __construct(PHPServer_EventLoop $eventLoop = null) {
        parent::__construct($eventLoop);

        cli_set_process_title($GLOBALS['argv'][0].':worker');
    }
}