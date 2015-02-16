<?php

namespace FraCasula\Bundle\PhpSandboxBundle\Services;

use Symfony\Component\Filesystem\Filesystem;
use FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandboxErrorHandler as ErrorHandler;

/**
 * Class PhpSandbox
 *
 * @package FraCasula\Bundle\PhpSandboxBundle\Services
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class PhpSandbox
{
    const CODE_SNIPPET_ERROR_REPORTING = "ini_set('display_errors', '1'); error_reporting(E_ALL);";

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $lastPhpCode;

    /**
     * @param string $cacheDir
     */
    public function __construct($cacheDir)
    {
        $this->cacheDir = $cacheDir;
        $this->filesystem = new Filesystem();
    }

    /**
     * @return string
     */
    public function getPhpBinary()
    {
        return PHP_BINARY;
    }

    /**
     * @return string cache dir
     */
    public function getCacheDir()
    {
        return $this->cacheDir;
    }

    /**
     * Returns the PHP Sandbox script dir
     *
     * @return string PHP Sandbox script dir
     */
    public function getPhpSandboxDir()
    {
        return $this->getCacheDir().'/fra_casula_php_sandbox';
    }

    /**
     * @return Filesystem
     */
    public function getFilesystem()
    {
        return $this->filesystem;
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
     * Creates the sandbox directory where new php scripts will be written.
     * The sandbox directory will be created into the kernel cache dir.
     */
    private function mkdir()
    {
        $this->getFilesystem()->mkdir(
            $this->getPhpSandboxDir()
        );
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
    private function preparePhpCode($code, $error_reporting = false, $forceStartTag = true)
    {
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

        $_PhpSandboxFullPath = $this->getPhpSandboxDir().DIRECTORY_SEPARATOR.$this->getUniqueToken($code).'.php';

        $this->mkdir();
        $this->preparePhpCode($code);

        /**
         * @todo Symfony2 Filesystem component dumpFile() not compatible with streams
         * @url https://github.com/symfony/symfony/issues/10018
         */
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
            $this->getFilesystem()->remove($_PhpSandboxFullPath);
            throw $e;
        }

        $_PhpSandBoxResult = ob_get_contents();
        ob_end_clean();

        if ($_PhpSandboxBufferBackup) {
            ob_start();
            echo $_PhpSandboxBufferBackup;
        }

        $this->getFilesystem()->remove($_PhpSandboxFullPath);

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
        $wdir = $this->getPhpSandboxDir();

        $descriptorSpec =
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];

        $this->mkdir();
        $process = proc_open($this->getPhpBinary(), $descriptorSpec, $pipes, $wdir, $variables);

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