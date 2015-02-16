<?php

namespace FraCasula\Bundle\PhpSandboxBundle\Tests\Services;

use PHPUnit_Framework_TestCase as TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox;
use FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandboxErrorHandler as ErrorHandler;

/**
 * Class PhpSandboxTest
 *
 * @package FraCasula\Bundle\PhpSandboxBundle\Tests\Services
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class PhpSandboxTest extends TestCase
{
    /**
     * @var PhpSandbox
     */
    private $phpSandbox;

    /**
     * @var vfsStreamDirectory
     */
    private $cacheDir;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->cacheDir = vfsStream::setup('cache');
        $this->phpSandbox = new PhpSandbox(vfsStream::url('cache'));
    }

    /**
     * @return \org\bovigo\vfs\vfsStreamContent
     */
    private function getSandboxDir()
    {
        $sandboxDir = $this->cacheDir->getChild('fra_casula_php_sandbox');
        $this->assertInstanceOf('\org\bovigo\vfs\vfsStreamContent', $sandboxDir);

        return $sandboxDir;
    }

    /**
     * @return PhpSandbox
     */
    private function getSandbox()
    {
        return $this->phpSandbox;
    }

    /**
     * Asserts that the sandbox directory does not contain any file
     */
    private function assertPhpSandboxDirIsEmpty()
    {
        $this->assertFalse($this->getSandboxDir()->hasChildren(), 'PHP Sandbox dir is not empty');
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::mkdir
     */
    public function testMkDir()
    {
        $this->getSandbox()->run('echo 1;');

        $this->assertTrue($this->getSandboxDir()->isWritable(vfsStream::OWNER_USER_1, vfsStream::GROUP_USER_1));
        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::run
     */
    public function testRunEcho()
    {
        $this->expectOutputString('6');

        echo $this->getSandbox()->run('echo 3 * 2;');

        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::run
     */
    public function testRunClassReference()
    {
        $this->expectOutputString('123');

        $php = <<<PHP
class Test
{
	public \$x;
}

\$one = new Test();
\$two= \$one;

\$two->x = 123;

echo \$one->x;
PHP;

        echo $this->getSandbox()->run($php);

        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::run
     */
    public function testRunNamespace()
    {
        $php = <<<PHP
namespace MyApp\MyTest\MyPackage {
	class Test
	{
		public function printTest(\$value) { echo \$value; }
	}
}

namespace MyApp\MyTest\Test {
	use MyApp\MyTest\MyPackage\Test;

	\$x = new Test();
	\$x->printTest('first test');
}
PHP;

        $this->assertEquals('first test', $this->getSandbox()->run($php));

        $expectedString = 'second value';

        $this->expectOutputString($expectedString);

        $test = new \MyApp\MyTest\MyPackage\Test();
        $test->printTest($expectedString);

        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::runStandalone
     */
    public function testRunStandalone()
    {
        $variables = [
            'arg1' => '3',
            'arg2' => '6',
            'arg3' => '9',
        ];

        $php = <<<PHP
\$arg1 = (int) \$_SERVER['arg1'];
\$arg2 = (int) \$_SERVER['arg2'];
\$arg3 = (int) \$_SERVER['arg3'];

echo (\$arg1 * \$arg2 * \$arg3);
PHP;

        $res = $this->getSandbox()->runStandalone($php, $variables);

        $this->assertEquals($res, '162');

        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @expectedException \FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxNotice
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::runStandalone
     */
    public function testRunStandaloneNotice()
    {
        $this->getSandbox()->runStandalone('echo $x[0];');
        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @expectedException \FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxWarning
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::runStandalone
     */
    public function testRunStandaloneWarning()
    {
        $this->getSandbox()->runStandalone('include("file_that_does_not_exist");');
        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::runStandalone
     */
    public function testRunStandaloneParseError()
    {
        $this->setExpectedException(
            '\FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxError',
            '',
            ErrorHandler::PHP_PARSE_ERROR
        );

        $this->getSandbox()->runStandalone('x x x');
        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::runStandalone
     */
    public function testRunStandaloneFatalError()
    {
        $this->setExpectedException(
            '\FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxError',
            '',
            ErrorHandler::PHP_FATAL_ERROR
        );

        $this->getSandbox()->runStandalone('call_to_undefined_function();');
        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::runStandalone
     */
    public function testRunStandaloneIsReallyStandalone()
    {
        $this->setExpectedException(
            '\FraCasula\Bundle\PhpSandboxBundle\Exception\PhpSandboxError',
            '',
            ErrorHandler::PHP_FATAL_ERROR
        );

        $php = <<<PHP
use FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandboxErrorHandler as ErrorHandler;

ErrorHandler::checkErrorLog('whatever');
PHP;

        $this->getSandbox()->runStandalone($php);

        $this->assertPhpSandboxDirIsEmpty();
    }

    /**
     * @covers \FraCasula\Bundle\PhpSandboxBundle\Services\PhpSandbox::runInBackground
     */
    public function testRunInBackground()
    {
        $writeDir = '/tmp/' . md5(microtime());
        mkdir($writeDir);

        $php = <<<PHP
\$index = (int) \$_SERVER['index'];
\$writeDir = trim(\$_SERVER['writeDir']);

\$filename = \$writeDir . DIRECTORY_SEPARATOR . "\$index.txt";

file_put_contents(\$filename, (string) \$index);
PHP;

        for ($i = 0; $i < 10; $i++) {
            $this->getSandbox()->runInBackground($php, ['index' => $i, 'writeDir' => $writeDir], false);
        }

        $time = time();
        $timeout = 10;
        $assertion = false;

        while (true) {
            $check = true;

            for ($i = 0; $i < 10; $i++) {
                if (!realpath($writeDir.DIRECTORY_SEPARATOR."$i.txt")) {
                    $check = false;
                }
            }

            if ($check) {
                $assertion = true;
                break;
            } else {
                if ((time() - $time) > $timeout) {
                    break;
                }
            }
        }

        for ($i = 0; $i < 10; $i++) {
            if (($filename = realpath($writeDir.DIRECTORY_SEPARATOR."$i.txt"))) {
                $this->assertEquals($i, file_get_contents($filename));
                unlink($filename);
            }
        }

        $this->assertTrue($assertion, "Unable to verify background file creation in $timeout seconds");

        rmdir($writeDir);
    }
}
