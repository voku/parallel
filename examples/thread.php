#!/usr/bin/env php
<?php
require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Loop;
use Amp\Parallel\Context\Thread;
use Amp\Parallel\Sync\Channel;
use function Amp\delay;

$timer = Loop::repeat(1000, function () {
    static $i;
    $i = $i ? ++$i : 1;
    print "Demonstrating how alive the parent is for the {$i}th time.\n";
});

try {
    // Create a new child thread that does some blocking stuff.
    $context = Thread::run(function (Channel $channel): int {
        \printf("Received the following from parent: %s\n", $channel->receive());

        print "Sleeping for 3 seconds...\n";
        \sleep(3); // Blocking call in thread.

        $channel->send("Data sent from child.");

        print "Sleeping for 2 seconds...\n";
        \sleep(2); // Blocking call in thread.

        return 42;
    });

    print "Waiting 2 seconds to send start data...\n";
    delay(2000);

    $context->send("Start data");

    \printf("Received the following from child: %s\n", $context->receive());
    \printf("Thread ended with value %d!\n", $context->join());
} finally {
    Loop::cancel($timer);
}
