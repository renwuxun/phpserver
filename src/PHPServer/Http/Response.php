<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 11-3 00003
 * Time: 15:42
 */
class PHPServer_Http_Response {

    protected $headerComplete = false;

    protected $header = '';

    protected $bodyType = '';

    protected $bodyLenght = 0;
    protected $recvedBodyLenght = 0;

    protected $body = '';

    protected $errstr = '';

    protected $chunkHeaderComplete = false;
    protected $chunkHeader = '';
    protected $chunkBodyLenght = 0;
    protected $recvedChunkBodyLenght = 0;

    public function reset() {
        $this->headerComplete = false;
        $this->header = '';
        $this->bodyType = '';
        $this->bodyLenght = 0;
        $this->recvedBodyLenght = 0;
        $this->body = '';
        $this->errstr = '';
        $this->chunkHeaderComplete = false;
        $this->chunkHeader = '';
        $this->chunkBodyLenght = 0;
        $this->recvedChunkBodyLenght = 0;
    }

    /**
     * @param $fp resource
     * @param $onFinish callable($header, $body)
     * @param $onHeaderFinish callable($header)
     * @param $onChunkFinish callable($chunk)
     */
    public function recv($fp, $onFinish, $onHeaderFinish = null, $onChunkFinish = null) {
        $this->errstr = '';

        // 先接收完报头
        if (!$this->headerComplete) {
            do {
                $s = fgets($fp, 1024);
                //echo "fgets: [$s]\n";
                if (!$s) {
                    if ($s === false) {
                        $this->errstr = 'fgets error '.__LINE__;
                        $info = @stream_get_meta_data($fp);
                        if (isset($info['timed_out']) && $info['timed_out']) {
                            $this->errstr = 'fgets timeout';
                        }
                    }
                    break;
                }
                $this->header .= $s;
                if (substr($this->header, -4) == "\r\n\r\n") {
                    $this->headerComplete = true;
                    if (is_callable($onHeaderFinish)) {
                        $onHeaderFinish($this->header);
                    }
                }
            } while (!$this->headerComplete);
        }

        if ($this->headerComplete) {

            // 得知body类型
            if (!$this->bodyType) {
                if (preg_match('/Content-Length:\s*\d+/i', $this->header)) {
                    $this->bodyType = 'unchunked';
                } elseif(preg_match('/Transfer-Encoding:\s*chunked/i', $this->header)) {
                    $this->bodyType = 'chunked';
                } else {
                    $this->errstr = 'unknown content type ['.$this->header.']';
                    return;
                }
            }

            // 根据不同body类型开始接收body
            switch ($this->bodyType) {
                case 'chunked':
                    $this->readChunkedContent($fp, $onFinish, $onChunkFinish);
                    break;
                case 'unchunked':
                    $this->readLenghtContent($fp, $onFinish);
                    break;
                default;
            }
        }
    }

    protected function readChunkedContent($fp, $onFinish, $onChunkFinish) {
        if (!$this->chunkHeaderComplete) {
            do {
                $s = fgets($fp, 64);
                //echo "fgets: [$s]\n";
                if (!$s) {
                    if ($s === false) {
                        $this->errstr = 'fgets error '.__LINE__;
                        $info = @stream_get_meta_data($fp);
                        if (isset($info['timed_out']) && $info['timed_out']) {
                            $this->errstr = 'fgets timeout';
                        }
                    }
                    break;
                }
                $this->chunkHeader .= $s;
                if (substr($this->chunkHeader, -2) == "\r\n") {
                    $this->chunkHeaderComplete = true;
                    //$this->body .= $this->chunkHeader;
                }
            } while (!$this->chunkHeaderComplete);
        }

        if ($this->chunkHeaderComplete) {

            if (!$this->chunkBodyLenght) {
                $this->chunkBodyLenght = hexdec(trim($this->chunkHeader)) + 2;
            }

            if ($this->chunkBodyLenght) {
                do {
                    $s = fread($fp, $this->chunkBodyLenght);
                    //echo "fread: [$s]\n";
                    if (!$s) {
                        if ($s === false) {
                            $this->errstr = 'fread error '.__LINE__;
                            $info = @stream_get_meta_data($fp);
                            if (isset($info['timed_out']) && $info['timed_out']) {
                                $this->errstr = 'fread timeout';
                            }
                        }
                        break;
                    }
                    $this->body .= $s;
                    $this->recvedChunkBodyLenght += strlen($s);
                } while ($this->recvedChunkBodyLenght < $this->chunkBodyLenght);

                if ($this->recvedChunkBodyLenght == $this->chunkBodyLenght) {
                    $bodyLen = strlen($this->body);
                    if (is_callable($onChunkFinish)) {
                        $onChunkFinish(substr($this->body, $bodyLen-$this->chunkBodyLenght, $this->chunkBodyLenght-2));
                    }
                    $this->body = substr($this->body, 0, $bodyLen-2); // 移除chunk后面的\r\n
                    if (2 == $this->chunkBodyLenght) { // 0\r\n\r\n
                        $onFinish($this->header, $this->body);
                        $this->headerComplete = false;
                        $this->header = '';
                        $this->body = '';
                        $this->bodyType = '';
                        $this->bodyLenght = 0;
                        $this->recvedBodyLenght = 0;
                    }
                    $this->chunkHeaderComplete = false;
                    $this->chunkHeader = '';
                    $this->chunkBodyLenght = 0;
                    $this->recvedChunkBodyLenght = 0;
                }
            }
        }
    }

    protected function readLenghtContent($fp, $onFinish) {
        if (!$this->bodyLenght) {
            if (preg_match('/Content-Length:\s*(\d+)/i', $this->header, $m)) {
                $this->bodyLenght = intval($m[1]);
            }
        }

        if ($this->bodyLenght) {
            do {
                $s = fread($fp, $this->bodyLenght);
                //echo "fread: [$s]\n";
                if (!$s) {
                    if ($s === false) {
                        $this->errstr = 'fread error '.__LINE__;
                        $info = @stream_get_meta_data($fp);
                        if (isset($info['timed_out']) && $info['timed_out']) {
                            $this->errstr = 'fread timeout';
                        }
                    }
                    break;
                }
                $this->body .= $s;
                $this->recvedBodyLenght += strlen($s);
            } while ($this->recvedBodyLenght < $this->bodyLenght);

            if ($this->recvedBodyLenght == $this->bodyLenght) {
                $onFinish($this->header, $this->body);
                $this->headerComplete = false;
                $this->header = '';
                $this->body = '';
                $this->bodyType = '';
                $this->bodyLenght = 0;
                $this->recvedBodyLenght = 0;
            }
        }
    }

    /**
     * @return string
     */
    public function getErrstr() {
        return $this->errstr;
    }

    /**
     * @return string
     */
    public function getHeader() {
        return $this->header;
    }

    /**
     * @return string
     */
    public function getBody() {
        return $this->body;
    }
}