<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerProcess;

class WorkerProcessTest extends AbstractWorkerTest
{
    protected function createWorker(): Worker
    {
        return new WorkerProcess;
    }
}
