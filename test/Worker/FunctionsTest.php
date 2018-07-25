<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker;
use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Pool;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerFactory;
use Amp\PHPUnit\TestCase;

class FunctionsTest extends TestCase
{
    public function testPool(): void
    {
        $pool = $this->createMock(Pool::class);

        Worker\pool($pool);

        $this->assertSame(Worker\pool(), $pool);
    }

    /**
     * @depends testPool
     */
    public function testEnqueue(): void
    {
        $pool = $this->createMock(Pool::class);
        $pool->method('enqueue')
            ->will($this->returnCallback(function (Task $task) {
                return $task->run($this->createMock(Environment::class));
            }));

        Worker\pool($pool);

        $this->assertSame(42, Worker\enqueue(new TestTask(42)));
    }

    /**
     * @depends testPool
     */
    public function testGet(): void
    {
        $pool = $this->createMock(Pool::class);
        $pool->expects($this->once())
            ->method('get')
            ->will($this->returnValue($this->createMock(Worker\Worker::class)));

        Worker\pool($pool);

        Worker\get();
    }

    public function testFactory(): void
    {
        $factory = $this->createMock(WorkerFactory::class);

        Worker\factory($factory);

        $this->assertSame(Worker\factory(), $factory);
    }

    /**
     * @depends testFactory
     */
    public function testCreate(): void
    {
        $factory = $this->createMock(WorkerFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->createMock(Worker\Worker::class)));

        Worker\factory($factory);

        Worker\create();
    }
}
