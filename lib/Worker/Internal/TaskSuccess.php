<?php

namespace Amp\Parallel\Worker\Internal;

/** @internal */
class TaskSuccess extends TaskResult {
    /** @var mixed Result of task. */
    private $result;

    public function __construct(string $id, $result) {
        parent::__construct($id);

        $this->result = $result;
    }

    public function get() {
        return $this->result;
    }
}
