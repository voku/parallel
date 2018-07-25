<?php

namespace Amp\Parallel\Test\Context;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\Thread;

/**
 * @group threading
 * @requires extension pthreads
 */
class ThreadTest extends AbstractContextTest
{
    public function createContext(callable $function): Context
    {
        return new Thread($function);
    }

    public function testSpawnStartsThread()
    {
        $thread = Thread::run(function () {
            usleep(100);
        });

        $this->assertTrue($thread->isRunning());

        $thread->join();
    }
}
