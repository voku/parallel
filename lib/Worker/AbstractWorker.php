<?php

namespace Amp\Parallel\Worker;

use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Worker\Internal\TaskResult;
use Concurrent\Awaitable;
use Concurrent\Deferred;
use Concurrent\Task as AsyncTask;

/**
 * Base class for most common types of task workers.
 */
abstract class AbstractWorker implements Worker
{
    /** @var Context */
    private $context;

    /** @var Awaitable|null */
    private $pendingStart;

    /** @var bool */
    private $shutdown = false;

    /** @var Awaitable|null */
    private $pendingJob;

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        if ($context->isRunning()) {
            throw new \Error("The context was already running");
        }

        $this->context = $context;
    }

    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->context->isRunning();
    }

    /**
     * {@inheritdoc}
     */
    public function isIdle(): bool
    {
        return $this->pendingStart === null && $this->pendingJob === null;
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue(Task $task)
    {
        if ($this->shutdown) {
            throw new StatusError("The worker has been shut down");
        }

        if (!$this->context->isRunning()) {
            $this->pendingStart = AsyncTask::async(function () {
                $this->context->start();
            });
        }

        $job = new Internal\Job($task);
        $id = $job->getId();

        $previousPendingJob = $this->pendingJob;
        $pendingJob = $this->pendingJob = AsyncTask::async(function () use ($job, $id, $previousPendingJob) {
            if ($this->pendingStart) {
                AsyncTask::await($this->pendingStart);
                $this->pendingStart = null;
            }

            if ($previousPendingJob) {
                try {
                    AsyncTask::await($previousPendingJob);
                } catch (\Throwable $exception) {
                    // Ignore error from prior job.
                }
            }

            if (!$this->context->isRunning()) {
                throw new WorkerException("The worker was shutdown");
            }

            $this->context->send($job);
            $result = $this->context->receive();

            if (!$result instanceof TaskResult) {
                $this->cancel();
            }

            if ($result->getId() !== $id) {
                $this->cancel();
            }

            return $result->get();
        });

        Deferred::transform($pendingJob, function () use ($pendingJob) {
            if ($this->pendingJob === $pendingJob) {
                $this->pendingJob = null;
            }
        });

        return AsyncTask::await($pendingJob);
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown(): int
    {
        if ($this->shutdown) {
            throw new StatusError("The worker is not running");
        }

        $this->shutdown = true;

        if (!$this->context->isRunning()) {
            return 0;
        }

        if ($this->pendingStart) {
            AsyncTask::await($this->pendingStart);
        }

        if ($this->pendingJob) {
            // If a task is currently running, wait for it to finish.
            try {
                AsyncTask::await($this->pendingJob);
            } catch (\Throwable $e) {
                // ignore here
            }
        }

        $this->context->send(0);

        return $this->context->join();
    }

    /**
     * {@inheritdoc}
     */
    public function kill(): void
    {
        $this->cancel();
    }

    /**
     * Cancels all pending tasks and kills the context.
     */
    protected function cancel(): void
    {
        if ($this->context->isRunning()) {
            $this->context->kill();
        }
    }
}
