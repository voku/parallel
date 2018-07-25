<?php

namespace Amp\Parallel\Test\Worker;

use Amp\Parallel\Worker\Internal\TaskSuccess;
use Amp\PHPUnit\TestCase;

class TaskSuccessTest extends TestCase
{
    public function testGetId(): void
    {
        $id = 'a';
        $result = new TaskSuccess($id, 1);
        $this->assertSame($id, $result->getId());
    }

    public function testPromise(): void
    {
        $value = 1;
        $result = new TaskSuccess('a', $value);
        $this->assertSame($value, $result->get());
    }
}
