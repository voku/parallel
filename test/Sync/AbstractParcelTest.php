<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Parcel;
use Amp\PHPUnit\TestCase;

abstract class AbstractParcelTest extends TestCase
{
    abstract protected function createParcel($value): Parcel;

    public function testUnwrapIsOfCorrectType(): void
    {
        $object = $this->createParcel(new \stdClass);
        $this->assertInstanceOf(\stdClass::class, $object->unwrap());
    }

    public function testUnwrapIsEqual(): void
    {
        $object = new \stdClass;
        $shared = $this->createParcel($object);
        $this->assertEquals($object, $shared->unwrap());
    }

    /**
     * @depends testUnwrapIsEqual
     */
    public function testSynchronized(): void
    {
        $parcel = $this->createParcel(0);

        $this->assertSame(1, $parcel->synchronized(function ($value) {
            $this->assertSame(0, $value);
            \usleep(10000);
            return 1;
        }));

        $this->assertSame(2, $parcel->synchronized(function ($value) {
            $this->assertSame(1, $value);
            \usleep(10000);
            return 2;
        }));
    }
}
