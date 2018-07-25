<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;
use function Amp\delay;

class TestTask implements Task
{
    private $returnValue;
    private $delay;

    public function __construct($returnValue, int $delay = 0)
    {
        $this->returnValue = $returnValue;
        $this->delay = $delay;
    }

    public function run(Environment $environment)
    {
        if ($this->delay) {
            delay($this->delay);
        }

        return $this->returnValue;
    }
}
