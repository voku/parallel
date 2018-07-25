<?php

namespace Amp\Parallel\Test\Context;

use function Amp\delay;
use Amp\Parallel\Context\Process;
use Amp\Parallel\Sync\PanicError;
use Amp\PHPUnit\TestCase;

class ProcessTest extends TestCase
{
    public function testBasicProcess(): void
    {
        $process = new Process([
            __DIR__ . "/test-process.php",
            "Test",
        ]);
        $process->start();

        $this->assertSame("Test", $process->join());
    }

    /**
     * @depends testBasicProcess
     */
    public function testFailingProcess(): void
    {
        $process = new Process(__DIR__ . "/test-process.php");
        $process->start();

        delay(1000);

        $this->expectException(PanicError::class);
        $this->expectExceptionMessage('No string provided');

        $process->join();
    }

    /**
     * @depends testBasicProcess
     */
    public function testInvalidScriptPath(): void
    {
        $process = new Process("test-process.php");
        $process->start();

        delay(1000);

        $this->expectException(PanicError::class);
        $this->expectExceptionMessage('No script found at \'test-process.php\'');

        $process->join();
    }

    /**
     * @depends testBasicProcess
     */
    public function testInvalidResult(): void
    {
        $process = new Process(__DIR__ . "/invalid-result-process.php");
        $process->start();

        delay(1000);

        $this->expectException(PanicError::class);
        $this->expectExceptionMessage('The given data cannot be sent because it is not serializable');

        $process->join();
    }

    /**
     * @depends testBasicProcess
     */
    public function testNoCallbackReturned(): void
    {
        $process = new Process(__DIR__ . "/no-callback-process.php");
        $process->start();

        delay(1000);

        $this->expectException(PanicError::class);
        $this->expectExceptionMessage('did not return a callable function');

        $process->join();
    }

    /**
     * @depends testBasicProcess
     */
    public function testParseError(): void
    {
        $process = new Process(__DIR__ . "/parse-error-process.inc");
        $process->start();

        delay(1000);

        $this->expectException(PanicError::class);
        $this->expectExceptionMessage('Uncaught ParseError in execution context');

        $process->join();
    }
}
