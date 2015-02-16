<?php

namespace FraCasula\Bundle\PhpSandboxBundle\Services;

use FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxNotice;
use FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxWarning;
use FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxError;
use FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxGenericError;

/**
 * Class PhpSandboxErrorHandler
 *
 * @package FraCasula\Bundle\PhpSandboxBundle\Services
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class PhpSandboxErrorHandler
{
    const PHP_PARSE_ERROR = 0x00001;
    const PHP_FATAL_ERROR = 0x00002;
    const PHP_WARNING = 0x00003;
    const PHP_NOTICE = 0x00004;
    const PHP_GENERIC_ERROR = 0x00005;

    /**
     * @var array
     */
    private static $patterns = array
    (
        self::PHP_PARSE_ERROR => '/PHP Parse error:/',
        self::PHP_FATAL_ERROR => '/PHP Fatal error:/',
        self::PHP_WARNING => '/PHP Warning:/',
        self::PHP_NOTICE => '/PHP Notice:/'
    );

    /**
     * Check the error log for the first PHP error and throws the relative exception
     * If no Notice, Warning, Parse Error or Fatal Error was found but the errorLog is not empty,
     * it throws a PhpSandboxGenericError exception
     *
     * @param string $errorLog
     */
    public static function checkErrorLog($errorLog)
    {
        if (is_string($errorLog) && isset($errorLog[0])) {
            foreach (self::$patterns as $errorType => $pattern) {
                if (preg_match($pattern, $errorLog)) {
                    self::throwException($errorType, $errorLog);
                }
            }
        }
    }

    /**
     * Returns the first error line
     *
     * @param string $errorLog
     * @return string First error line
     */
    private static function extractFirstLine($errorLog)
    {
        if (is_string($errorLog) && isset($errorLog[0])) {
            $tmp = explode("\n", $errorLog);

            if (is_array($tmp) && isset($tmp[0])) {
                return trim($tmp[0]);
            }
        }

        return $errorLog;
    }

    /**
     * Check the error type and throws the relative custom exception
     *
     * @param integer $errorType
     * @param string $errorLog
     * @throws PhpSandboxNotice
     * @throws PhpSandboxWarning
     * @throws PhpSandboxError
     * @throws PhpSandboxGenericError
     */
    public static function throwException($errorType, $errorLog)
    {
        $errorMessage = self::extractFirstLine($errorLog);

        switch ($errorType) {
            case self::PHP_NOTICE:
                throw new PhpSandboxNotice($errorMessage, $errorType);
                break;
            case self::PHP_WARNING:
                throw new PhpSandboxWarning($errorMessage, $errorType);
                break;
            case self::PHP_PARSE_ERROR:
                throw new PhpSandboxError($errorMessage, $errorType);
                break;
            case self::PHP_FATAL_ERROR:
                throw new PhpSandboxError($errorMessage, $errorType);
                break;
            default:
                throw new PhpSandboxGenericError($errorType, $errorMessage);
        }
    }
}