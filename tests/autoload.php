<?php
/**
 * Created by PhpStorm.
 * User: mofan
 * Date: 2016/8/5 0005
 * Time: 18:03
 */


spl_autoload_register(function($claname){
    if ('PHPServer_' == substr($claname, 0, 10)) {
        require (__DIR__.'/../src/'.strtr($claname, '_', '/').'.php');
    }
});