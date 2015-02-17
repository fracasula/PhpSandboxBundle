<?php

namespace FraCasula\Bundle\PhpSandboxBundle\Stream;

/**
 * Class SandboxStream
 * @package FraCasula\Bundle\PhpSandboxBundle\Stream
 */
class SandboxStream
{
    /**
     * @var int
     */
    private $position;

    /**
     * @var string
     */
    private $varname;

    /**
     * @return mixed
     */
    private function getVar()
    {
        return $GLOBALS[$this->varname];
    }

    /**
     * {@inheritdoc}
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $url = parse_url($path);
        $this->varname = $url['host'];
        $this->position = 0;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_read($count)
    {
        $return = substr($this->getVar(), $this->position, $count);
        $this->position += strlen($return);

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_write($data)
    {
        $var =& $GLOBALS[$this->varname];
        $length = strlen($data);
        $var = substr($var, 0, $this->position).$data.substr($var, $this->position += $length);

        return $length;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_tell()
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function stream_eof()
    {
        return ($this->position >= strlen($this->getVar()));
    }

    /**
     * {@inheritdoc}
     */
    public function stream_stat()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function stream_seek($offset, $whence)
    {
        $length = strlen($this->getVar());

        switch ($whence) {
            case SEEK_SET:
                $newPos = $offset;
                break;
            case SEEK_CUR:
                $newPos = $this->position + $offset;
                break;
            case SEEK_END:
                $newPos = $length + $offset;
                break;
            default:
                return false;
        }

        $ret = ($newPos >= 0 && $newPos <= $length);

        if ($ret) {
            $this->position = $newPos;
        }

        return $ret;
    }
}