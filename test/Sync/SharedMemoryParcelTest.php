<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\Parcel;
use Amp\Parallel\Sync\SharedMemoryParcel;

/**
 * @requires extension shmop
 * @requires extension sysvmsg
 */
class SharedMemoryParcelTest extends AbstractParcelTest
{
    private const ID = __CLASS__;

    private $parcel;

    protected function createParcel($value): Parcel
    {
        $this->parcel = SharedMemoryParcel::create(self::ID, $value);
        return $this->parcel;
    }

    public function tearDown()
    {
        $this->parcel = null;
    }

    public function testObjectOverflowMoved(): void
    {
        $object = SharedMemoryParcel::create(self::ID, 'hi', 2);
        $object->synchronized(function () {
            return 'hello world';
        });

        $this->assertEquals('hello world', $object->unwrap());
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testSetInSeparateProcess(): void
    {
        $object = SharedMemoryParcel::create(self::ID, 42);

        $this->doInFork(function () use ($object) {
            $object->synchronized(function ($value) {
                return $value + 1;
            });
        });

        $this->assertEquals(43, $object->unwrap());
    }

    /**
     * @group posix
     * @requires extension pcntl
     */
    public function testInSeparateProcess(): void
    {
        $parcel = SharedMemoryParcel::create(self::ID, 42);

        $this->doInFork(function () {
            $parcel = SharedMemoryParcel::use(self::ID);
            $this->assertSame(43, $parcel->synchronized(function ($value) {
                $this->assertSame(42, $value);

                return $value + 1;
            }));
        });

        $this->assertSame(43, $parcel->unwrap());
    }
}
