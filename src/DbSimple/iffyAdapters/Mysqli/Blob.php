<?php
/**
 * User: orbisnull
 * Date: 13.09.13
 */

namespace DbSimple\Mysqli;

use DbSimple\BlobInteface;

class Blob implements BlobInteface
{
    // MySQL does not support separate BLOB fetching.
    private $blobdata = null;
    private $curSeek = 0;

    public function __construct(&$database, $blobdata=null)
    {
        $this->blobdata = $blobdata;
        $this->curSeek = 0;
    }

    public function read($len)
    {
        $p = $this->curSeek;
        $this->curSeek = min($this->curSeek + $len, strlen($this->blobdata));
        return substr($this->blobdata, $p, $len);
    }

    public function write($data)
    {
        $this->blobdata .= $data;
    }

    public function close()
    {
        return $this->blobdata;
    }

    public function length()
    {
        return strlen($this->blobdata);
    }
}