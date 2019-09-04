<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationToken;

/**
 * A runnable unit of execution.
 */
interface Task
{
    /**
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine or return a promise, can also be a regular function returning a value.
     *
     * @param Environment       $environment
     * @param CancellationToken $token
     *
     * @return mixed|\Amp\Promise|\Generator
     */
    public function run(Environment $environment, CancellationToken $token);
}
