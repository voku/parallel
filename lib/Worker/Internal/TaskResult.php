<?php

namespace Amp\Parallel\Worker\Internal;

/** @internal */
abstract class TaskResult
{
    /** @var string Task identifier. */
    private $id;

    /**
     * @param string $id Task identifier.
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return string Task identifier.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return mixed Resolved with the task result or failure reason.
     */
    abstract public function get();
}
