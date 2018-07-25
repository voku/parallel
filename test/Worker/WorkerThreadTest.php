<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerThread;

/**
 * @group threading
 * @requires extension pthreads
 */
class WorkerThreadTest extends AbstractWorkerTest
{
    protected function createWorker(): Worker
    {
        return new WorkerThread;
    }
}
