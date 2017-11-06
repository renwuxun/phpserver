<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 10-24 00024
 * Time: 14:46
 *
 * @method PHPServer_Timer top
 */
class PHPServer_TimerHeap extends SplMinHeap {

    /**
     * @param PHPServer_Timer $v1
     * @param PHPServer_Timer $v2
     * @return bool
     */
    public function compare($v1, $v2) {
        return $v1->fireAt - $v2->fireAt;
    }
}