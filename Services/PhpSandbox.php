<?php

namespace FraCasula\Bundle\PhpSandboxBundle\Services;

use FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandboxErrorHandler as ErrorHandler;

/**
 * Class PhpSandbox
 *
 * @package FraCasula\Bundle\PhpSandboxBundle\Services
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class PhpSandbox
{
    const WRAPPER = 'sandbox';
    const CODE_SNIPPET_ERROR_REPORTING = "ini_set('display_errors', '1'); error_reporting(E_ALL);";

    /**
     * @var string
     */
    private $lastPhpCode;

    /**
     * Constructor
     *
     * @param string $streamWrapperClassName
     */
    public function __construct($streamWrapperClassName)
    {
        $registered = in_array(self::WRAPPER, stream_get_wrappers());

        if (!$registered) {
            stream_wrapper_register(self::WRAPPER, $streamWrapperClassName);
        }
    }

    /**
     * @return string
     */
    public function getPhpBinary()
    {
        return PHP_BINARY;
    }

    /**
     * @param $code
     * @return $this
     */
    private function setLastPhpCode($code)
    {
        $this->lastPhpCode = $code;

        return $this;
    }

    /**
     * Returns the last PHP code executed
     *
     * @return string $phpCode
     */
    public function getLastPhpCode()
    {
        return $this->lastPhpCode;
    }

    /**
     * Returns an unique token to create unique filenames
     *
     * @param string $salt If not specified microtime() will be used
     * @return string Unique token
     */
    private function getUniqueToken($salt = null)
    {
        if (!$salt) {
            $salt = microtime();
        }

        return md5($salt.uniqid());
    }

    /**
     * Prepare the PHP code adding the start tag <?php if it doesn't exists
     * and adding error_reporting option
     *
     * WARNING: short_open_tag is not allowed: use <?php or leave empty
     *
     * @param string $code PHP Code
     * @param boolean $error_reporting Enable the error_reporting(E_ALL)
     * @param boolean $forceStartTag
     * @return string PHP Code with starting tag <?php
     */
    private function preparePhpCode($code, $error_reporting = false, $forceStartTag = true) {
        $errInstruction = self::CODE_SNIPPET_ERROR_REPORTING;
        $err = $error_reporting ? $errInstruction : '';

        $code = ($err != '' ? trim($err).' ' : '').trim($code);

        if ($forceStartTag && strpos($code, '<?php') === false) {
            $code = '<?php '.trim($code);
        }

        $this->setLastPhpCode($code);

        return $code;
    }

    /**
     * @return string
     */
    public function getSanboxDir()
    {
        return self::WRAPPER.'://';
    }

    /**
     * Runs the PHP Code in the current environment.
     * Therefore classes and functions available in your script are also available in the new PHP code.
     *
     * @param string $code PHP Code
     * @param array $variables Array of variables to pass to the new PHP script
     * @return string Script output
     * @throws \Exception
     */
    public function run($code, $variables = [])
    {
        $_SANDBOX = $variables;
        unset($variables);

        $_PhpSandboxFullPath = $this->getSanboxDir().$this->getUniqueToken($code).'.php';

        $this->preparePhpCode($code);

        file_put_contents($_PhpSandboxFullPath, $this->getLastPhpCode());

        $_PhpSandboxBufferBackup = null;

        if (ob_get_length()) {
            $_PhpSandboxBufferBackup = ob_get_contents();
            ob_end_clean();
        }

        ob_start();

        try {
            include_once($_PhpSandboxFullPath);
        } catch (\Exception $e) {
            throw $e;
        }

        $_PhpSandBoxResult = ob_get_contents();
        ob_end_clean();

        if ($_PhpSandboxBufferBackup) {
            ob_start();
            echo $_PhpSandboxBufferBackup;
        }

        return $_PhpSandBoxResult;
    }

    /**
     * Runs the PHP Code in a standalone process.
     * Therefore classes and functions available in your script are NOT available in the new PHP code.
     *
     * @param string $code PHP Code
     * @param array $variables Array of variables to pass to the new PHP script
     * @return string Script output
     * @throws \Exception
     */
    public function runStandalone($code, $variables = [])
    {
        $stream = null;
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($this->getPhpBinary(), $descriptorSpec, $pipes, null, $variables);

        if (is_resource($process)) {
            $this->preparePhpCode($code, false);

            fwrite($pipes[0], $this->getLastPhpCode());
            fclose($pipes[0]);

            $stream = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            proc_close($process);

            ErrorHandler::checkErrorLog($errors);
        }

        return $stream;
    }

    /**
     * Runs the PHP Code in a standalone process and in background.
     * Therefore classes and functions available in your script are NOT available in the new PHP code
     * and the parent script will not wait for the child response.
     *
     * @param string $code PHP Code
     * @param array $variables Array of variables to pass to the new PHP script
     * @param boolean $debug If TRUE the parent script waits for the child execution and response
     * @throws \Exception If is not possible to fork the PHP process
     */
    public function runInBackground($code, $variables = [], $debug = false)
    {
        $this->preparePhpCode($code, false, false);

        $pid = pcntl_fork();

        if ($pid == -1) {
            throw new \Exception('Could not fork');
        } else {
            if ($pid) {
                if ($debug) {
                    pcntl_waitpid($pid, $status);
                }
            } else {
                pcntl_exec($this->getPhpBinary(), ['-r', $this->getLastPhpCode()], $variables);
            }
        }
    }
}