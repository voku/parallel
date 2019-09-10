<?php

namespace Amp\Parallel\Worker;

use Amp\CancelledException;

final class TaskCancelledException extends CancelledException
{
    /** @var string Class name of exception thrown from task. */
    private $name;

    /** @var string Stack trace of the exception thrown from task. */
    private $trace;

    /**
     * @param string          $name     The exception class name.
     * @param string          $trace    The exception stack trace.
     * @param \Throwable|null $previous Previous exception.
     */
    public function __construct(string $name, string $trace = '', \Throwable $previous = null)
    {
        parent::__construct($previous);

        $this->name = $name;
        $this->trace = $trace;
    }

    /**
     * Returns the class name of the exception thrown from the task.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the stack trace at the point the exception was thrown in the task.
     *
     * @return string
     */
    public function getWorkerTrace(): string
    {
        return $this->trace;
    }
}
