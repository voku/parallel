<?php

namespace Amp\Parallel\Worker\Internal;

use Amp\CancelledException;
use Amp\Failure;
use Amp\Parallel\Worker\TaskCancelledException;
use Amp\Promise;

/** @internal */
final class TaskCancelled extends TaskResult
{
    /** @var string */
    private $type;

    /** @var int|string */
    private $code;

    /** @var array */
    private $trace;

    /** @var TaskFailure|null */
    private $previous;

    public function __construct(string $id, CancelledException $exception)
    {
        parent::__construct($id);
        $this->type = \get_class($exception);
        $this->code = $exception->getCode();
        $this->trace = $exception->getTraceAsString();

        if ($previous = $exception->getPrevious()) {
            $this->previous = new TaskFailure($id, $previous);
        }
    }

    public function promise(): Promise
    {
        $previous = $this->previous ? $this->previous->createException() : null;

        return new Failure(new TaskCancelledException(
            $this->type,
            $this->trace,
            $previous
        ));
    }
}
