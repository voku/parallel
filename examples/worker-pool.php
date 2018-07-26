#!/usr/bin/env php
<?php

require \dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Loop;
use Amp\Parallel\Example\BlockingTask;
use Amp\Parallel\Worker\DefaultPool;
use Concurrent\Task;
use function Concurrent\all;

// A variable to store our fetched results
$results = [];

// We can first define tasks and then run them
$tasks = [
    new BlockingTask('file_get_contents', 'http://php.net'),
    new BlockingTask('file_get_contents', 'https://amphp.org'),
    new BlockingTask('file_get_contents', 'https://github.com'),
];

$timer = Loop::repeat(200, function () {
    \printf(".");
});
Loop::unreference($timer);

$pool = new DefaultPool;

$awaitables = [];

foreach ($tasks as $task) {
    $awaitables[] = Task::async(function () use ($pool, $task, &$results) {
        $result = $pool->enqueue($task);
        $url = $task->getArgs()[0];
        \printf("\nRead from %s: %d bytes\n", $url, \strlen($result));
        $results[$url] = $result;
    });
}

Task::await(all($awaitables));

return $pool->shutdown();

echo "\nResult array keys:\n";
echo \var_export(\array_keys($results), true);
