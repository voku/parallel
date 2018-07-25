<?php

namespace Amp\Parallel\Sync;

use Amp\Sync\ThreadedMutex;

/**
 * A thread-safe container that shares a value between multiple threads.
 */
class ThreadedParcel implements Parcel
{
    /** @var ThreadedMutex */
    private $mutex;

    /** @var \Threaded */
    private $storage;

    /**
     * Creates a new shared object container.
     *
     * @param mixed $value The value to store in the container.
     */
    public function __construct($value)
    {
        $this->mutex = new ThreadedMutex;
        $this->storage = new Internal\ParcelStorage($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unwrap()
    {
        return $this->storage->get();
    }

    /**
     * {@inheritdoc}
     */
    public function synchronized(callable $callback)
    {
        /** @var \Amp\Sync\Lock $lock */
        $lock = $this->mutex->acquire();

        try {
            $result = $callback($this->storage->get());

            if ($result !== null) {
                $this->storage->set($result);
            }
        } finally {
            $lock->release();
        }

        return $result;
    }
}
