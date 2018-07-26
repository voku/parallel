<?php

namespace Amp\Parallel\Worker;

/**
 * A runnable unit of execution.
 */
interface Task
{
    /**
     * Runs the task inside the caller's context.
     *
     * Does not have to be a coroutine, can also be a regular function returning a value.
     *
     * @param Environment
     *
     * @return mixed
     */
    public function run(Environment $environment);
}
