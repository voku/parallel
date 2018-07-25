<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\SerializationException;
use Amp\Parallel\Worker\Internal\Job;

class TaskRunner
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
     * @return mixed
     *
     * @throws ChannelException
     * @throws SerializationException
     */
    public function run()
    {
        $job = $this->channel->receive();

        while ($job instanceof Internal\Job) {
            try {
                $result = $job->getTask()->run($this->environment);
                $result = new Internal\TaskSuccess($job->getId(), $result);
            } catch (\Throwable $exception) {
                $result = new Internal\TaskFailure($job->getId(), $exception);
            }

            $job = null; // Free memory from last job.

            $this->channel->send($result);

            $result = null; // Free memory from last result.

            $job = $this->channel->receive();
        }

        return $job;
    }
}
