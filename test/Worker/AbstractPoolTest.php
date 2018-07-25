<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Loop;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\PHPUnit\TestCase;
use Concurrent\Awaitable;
use Concurrent\Task as AsyncTask;
use function Concurrent\all;

abstract class AbstractPoolTest extends TestCase
{
    /**
     * @param int $max
     *
     * @return Pool
     */
    abstract protected function createPool($max = Pool::DEFAULT_MAX_SIZE): Pool;

    public function testIsRunning(): void
    {
        $pool = $this->createPool();

        $this->assertTrue($pool->isRunning());

        $pool->shutdown();
        $this->assertFalse($pool->isRunning());
    }

    public function testIsIdleOnStart(): void
    {
        $pool = $this->createPool();

        $this->assertTrue($pool->isIdle());

        $pool->shutdown();
    }

    public function testGetMaxSize(): void
    {
        $pool = $this->createPool(17);
        $this->assertEquals(17, $pool->getMaxSize());
    }

    public function testWorkersIdleOnStart(): void
    {
        $pool = $this->createPool();

        $this->assertEquals(0, $pool->getIdleWorkerCount());

        $pool->shutdown();
    }

    public function testEnqueue(): void
    {
        $pool = $this->createPool();

        $returnValue = $pool->enqueue(new TestTask(42));
        $this->assertEquals(42, $returnValue);

        $pool->shutdown();
    }

    public function testEnqueueMultiple(): void
    {
        Loop::run(function () {
            $pool = $this->createPool();

            $values = AsyncTask::await(all([
                AsyncTask::async(function () use ($pool) {
                    return $pool->enqueue(new TestTask(42));
                }),
                AsyncTask::async(function () use ($pool) {
                    return $pool->enqueue(new TestTask(56));
                }),
                AsyncTask::async(function () use ($pool) {
                    return $pool->enqueue(new TestTask(72));
                }),
            ]));

            $this->assertEquals([42, 56, 72], $values);

            $pool->shutdown();
        });
    }

    public function testKill(): void
    {
        $pool = $this->createPool();

        $this->assertRunTimeLessThan([$pool, 'kill'], 1000);
        $this->assertFalse($pool->isRunning());
    }

    public function testGet(): void
    {
        $pool = $this->createPool();

        $worker = $pool->get();

        $this->assertFalse($worker->isRunning());
        $this->assertTrue($worker->isIdle());

        $this->assertSame(42, $worker->enqueue(new TestTask(42)));

        $worker->shutdown();

        $worker->kill();
    }

    public function testBusyPool(): void
    {
        $pool = $this->createPool(2);

        $values = [42, 56, 72];
        $tasks = \array_map(function (int $value): Task {
            return new TestTask($value);
        }, $values);

        $awaitables = \array_map(function (Task $task) use ($pool): Awaitable {
            return AsyncTask::async(function () use ($pool, $task) {
                return $pool->enqueue($task);
            });
        }, $tasks);

        $this->assertSame($values, AsyncTask::await(all($awaitables)));

        $awaitables = \array_map(function (Task $task) use ($pool): Awaitable {
            return AsyncTask::async(function () use ($pool, $task) {
                return $pool->enqueue($task);
            });
        }, $tasks);

        $this->assertSame($values, AsyncTask::await(all($awaitables)));

        $pool->shutdown();
    }

    public function testCleanGarbageCollection(): void
    {
        // See https://github.com/amphp/parallel-functions/issues/5
        for ($i = 0; $i < 3; $i++) {
            $pool = $this->createPool(32);

            $values = \range(1, 50);
            $tasks = \array_map(function (int $value): Task {
                return new TestTask($value);
            }, $values);

            $awaitables = \array_map(function (Task $task) use ($pool): Awaitable {
                return AsyncTask::async(function () use ($pool, $task) {
                    return $pool->enqueue($task);
                });
            }, $tasks);

            $this->assertSame($values, AsyncTask::await(all($awaitables)));
        }
    }
}
