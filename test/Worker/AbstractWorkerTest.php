<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\TaskError;
use Amp\Parallel\Worker\Worker;
use Amp\PHPUnit\TestCase;
use Concurrent\Task as AsyncTask;
use function Amp\rethrow;
use function Concurrent\all;

class NonAutoloadableTask implements Task
{
    public function run(Environment $environment)
    {
        return 1;
    }
}

abstract class AbstractWorkerTest extends TestCase
{
    abstract protected function createWorker(): Worker;

    public function testWorkerConstantDefined(): void
    {
        $worker = $this->createWorker();
        $this->assertTrue($worker->enqueue(new ConstantTask));
        $worker->shutdown();
    }

    public function testIsRunning(): void
    {
        $worker = $this->createWorker();
        $this->assertFalse($worker->isRunning());

        $worker->enqueue(new TestTask(42)); // Enqueue a task to start the worker.

        $this->assertTrue($worker->isRunning());

        $worker->shutdown();
        $this->assertFalse($worker->isRunning());
    }

    public function testIsIdleOnStart(): void
    {
        $worker = $this->createWorker();

        $this->assertTrue($worker->isIdle());

        $worker->shutdown();
    }

    public function testEnqueue(): void
    {
        $worker = $this->createWorker();

        $returnValue = $worker->enqueue(new TestTask(42));
        $this->assertEquals(42, $returnValue);

        $worker->shutdown();
    }

    public function testEnqueueMultipleSynchronous(): void
    {
        $this->markTestSkipped("Segfaults");

        $worker = $this->createWorker();

        $values = AsyncTask::await(all([
            AsyncTask::async(function () use ($worker) {
                return $worker->enqueue(new TestTask(42));
            }),
            AsyncTask::async(function () use ($worker) {
                return $worker->enqueue(new TestTask(56));
            }),
            AsyncTask::async(function () use ($worker) {
                return $worker->enqueue(new TestTask(72));
            }),
        ]));

        $this->assertEquals([42, 56, 72], $values);

        $worker->shutdown();
    }

    public function testNotIdleOnEnqueue(): void
    {
        $worker = $this->createWorker();

        $awaitable = AsyncTask::async(function () use ($worker) {
            $worker->enqueue(new TestTask(42));
        });

        rethrow(AsyncTask::async(function () use ($worker) {
            $this->assertFalse($worker->isIdle());
        }));

        AsyncTask::await($awaitable);

        $worker->shutdown();
    }

    public function testKill(): void
    {
        $worker = $this->createWorker();

        $worker->enqueue(new TestTask(42));

        $start = \microtime(true);
        $worker->kill();
        $this->assertLessThanOrEqual(0.25, \microtime(true) - $start);
        $this->assertFalse($worker->isRunning());
    }

    public function testNonAutoloadableTask(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new NonAutoloadableTask);
            $this->fail("Tasks that cannot be autoloaded should throw an exception");
        } catch (TaskError $exception) {
            $this->assertSame("Error", $exception->getName());
            $this->assertGreaterThan(0, \strpos($exception->getMessage(), \sprintf("Classes implementing %s", Task::class)));
        }

        $worker->shutdown();
    }

    public function testUnserializableTask(): void
    {
        $worker = $this->createWorker();

        try {
            $worker->enqueue(new class implements Task
            { // Anonymous classes are not serializable.
                public function run(Environment $environment)
                {
                }
            });
            $this->fail("Tasks that cannot be autoloaded should throw an exception");
        } catch (SerializationException $exception) {
            $this->assertSame(0, \strpos($exception->getMessage(), "The given data cannot be sent because it is not serializable"));
        }

        $worker->shutdown();
    }

    public function testUnserializableTaskFollowedByValidTask(): void
    {
        $worker = $this->createWorker();

        AsyncTask::async(function () use ($worker) {
            // Anonymous classes are not serializable.
            $task = new class implements Task
            {
                public function run(Environment $environment)
                {
                }
            };

            $worker->enqueue($task);
        });

        $this->assertSame(42, $worker->enqueue(new TestTask(42)));

        $worker->shutdown();
    }
}
