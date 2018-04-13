<?php

/**
 * Created by PhpStorm.
 * User: wuxun.ren
 * Date: 4-13 00013
 * Time: 16:06
 */
class PHPServer_PidFile {

    /**
     * @var resource
     */
    protected $fp;

    /**
     * @var string
     */
    protected $file;

    /**
     * PHPServer_PidFile constructor.
     * @param $file string
     */
    public function __construct($file) {
        $this->file = $file;
    }

    public function tryLock() {
        $path = substr($this->file, 0, strrpos($this->file, '/'));
        if ($path != '' && !is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $this->fp = fopen($this->file, "a+"); // 你把$fp申明为局部变量试试，会有奇效，嘿嘿嘿
        if (flock($this->fp, LOCK_EX|LOCK_NB)) {
            ftruncate($this->fp, 0);
            fwrite($this->fp, posix_getpid());
            return true;
        } else {
            $this->close();
            return false;
        }
    }

    public function unLock() {
        flock($this->fp, LOCK_UN);
    }

    public function close() {
        if (is_resource($this->fp)) {
            fclose($this->fp);
            $this->fp = null;
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}
