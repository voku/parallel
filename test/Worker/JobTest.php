<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\Job;
use Amp\PHPUnit\TestCase;

class JobTest extends TestCase
{
    public function testGetJob(): void
    {
        $task = new TestTask(42);
        $job = new Job($task);
        $this->assertSame($task, $job->getTask());
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Classes implementing Amp\Parallel\Worker\Task must be autoloadable by the Composer
     *     autoloader
     */
    public function testUnserialiableClass(): void
    {
        $task = new TestTask(42);
        $job = new Job($task);
        $serialized = \serialize($job);
        $job = \unserialize($serialized, ['allowed_classes' => [Job::class]]);
        $job->getTask();
    }
}
