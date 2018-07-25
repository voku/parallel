<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskFailure;
use Amp\PHPUnit\TestCase;

class TaskFailureTest extends TestCase
{
    /**
     * @expectedException \Amp\Parallel\Worker\TaskException
     * @expectedExceptionMessage Uncaught Exception in worker
     */
    public function testWithException(): void
    {
        $exception = new \Exception("Message", 1);
        $result = new TaskFailure('a', $exception);
        $result->get();
    }

    /**
     * @expectedException \Amp\Parallel\Worker\TaskError
     * @expectedExceptionMessage Uncaught Error in worker
     */
    public function testWithError(): void
    {
        $exception = new \Error("Message", 1);
        $result = new TaskFailure('a', $exception);
        $result->get();
    }
}
