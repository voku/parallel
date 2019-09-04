<?php

namespace Amp\Parallel\Worker;

use Amp\CancellationTokenSource;
use Amp\Coroutine;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\SerializationException;
use Amp\Promise;
use function Amp\call;

final class TaskRunner
{
    /** @var Channel */
    private $channel;

    /** @var Environment */
    private $environment;

    public function __construct(Channel $channel, Environment $environment)
    {
        $this->channel = $channel;
        $this->environment = $environment;
    }

    /**
     * Runs the task runner, receiving tasks from the parent and sending the result of those tasks.
     *
     * @return \Amp\Promise<null>
     */
    public function run(): Promise
    {
        return new Coroutine($this->execute());
    }

    /**
     * @return \Generator
     */
    private function execute(): \Generator
    {
        $job = yield $this->channel->receive();

        while ($job instanceof Internal\Job) {
            $receive = $this->channel->receive();
            $source = new CancellationTokenSource;
            $resolved = false;

            try {
                $receive->onResolve(static function (?\Throwable $exception) use (&$resolved, $source): void {
                    if (!$resolved) {
                        $source->cancel($exception);
                    }
                });

                $result = new Internal\TaskSuccess(
                    $job->getId(),
                    yield call([$job->getTask(), "run"], $this->environment, $source->getToken())
                );
            } catch (\Throwable $exception) {
                $result = new Internal\TaskFailure($job->getId(), $exception);
            } finally {
                $resolved = true;
            }

            $job = $source = null; // Free memory from last job.

            try {
                yield $this->channel->send($result);
            } catch (SerializationException $exception) {
                // Could not serialize task result.
                yield $this->channel->send(new Internal\TaskFailure($result->getId(), $exception));
            }

            $result = null; // Free memory from last result.

            while (!($job = yield $receive) instanceof Internal\Job && $job !== null) {
                $receive = $this->channel->receive();
            }
        }
    }
}
