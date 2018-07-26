#!/usr/bin/env php
<?php
require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\Parcel;
use Amp\Parallel\Sync\ThreadedParcel;
use function Amp\delay;

$parcel = new ThreadedParcel(1);

$context = Thread::run(function (Channel $channel, Parcel $parcel) {
    $value = $parcel->synchronized(function (int $value) {
        return $value + 1;
    });

    \printf("Value after modifying in child thread: %s\n", $value);

    delay(500); // Main thread should access parcel during this time.

    // Unwrapping the parcel now should give value from main thread.
    \printf("Value in child thread after being modified in main thread: %s\n", $parcel->unwrap());

    $parcel->synchronized(function (int $value) {
        return $value + 1;
    });
}, $parcel);

delay(100); // Give the thread time to start and access the parcel.

$parcel->synchronized(function (int $value) {
    return $value + 1;
});

$context->join(); // Wait for child thread to finish.

\printf("Final value of parcel: %d\n", $parcel->unwrap());
