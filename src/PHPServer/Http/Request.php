<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 11-3 00003
 * Time: 15:42
 */
class PHPServer_Http_Request {

    protected $package = '';
    protected $packageLen = 0;
    protected $packageSentLen = 0;

    protected $errstr = '';

    public function setPackage($package) {
        $this->package = $package;
        $this->packageLen = strlen($package);
        $this->packageSentLen = 0;
    }

    /**
     * @param $fp resource
     * @param $onFinish callable($package)
     */
    public function send($fp, $onFinish) {
        $this->errstr = '';

        do {
            $n = fwrite($fp, substr($this->package, $this->packageSentLen));
            if (!$n) {
                if ($n === false) {
                    $this->errstr = 'fwrite error '.__LINE__;
                }
                break;
            }
            $this->packageSentLen += $n;
        } while ($this->packageSentLen < $this->packageLen);

        if ($this->packageSentLen == $this->packageLen) {
            $onFinish($this->package);
            $this->packageLen = strlen($this->package);
            $this->packageSentLen = 0;
        }
    }

    /**
     * @return int
     */
    public function getPackageSentLen() {
        return $this->packageSentLen;
    }

    /**
     * @return int
     */
    public function getPackageLen() {
        return $this->packageLen;
    }

    /**
     * @return string
     */
    public function getErrstr() {
        return $this->errstr;
    }
}