<?php

namespace Amp\Parallel\Test\Context;

use Amp\Loop;
use Amp\Parallel\Context\Context;
use Amp\Parallel\Context\ContextException;
use Amp\Parallel\Context\StatusError;
use Amp\Parallel\Sync\Channel;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ExitSuccess;
use Amp\Parallel\Sync\PanicError;
use Amp\Parallel\Sync\SynchronizationError;
use Amp\PHPUnit\TestCase;

abstract class AbstractContextTest extends TestCase
{
    abstract public function createContext(callable $function): Context;

    public function testIsRunning(): void
    {
        Loop::run(function () {
            $context = $this->createContext(function () {
                usleep(100);
            });

            $this->assertFalse($context->isRunning());

            $context->start();

            $this->assertTrue($context->isRunning());

            $context->join();

            $this->assertFalse($context->isRunning());
        });
    }

    public function testKill(): void
    {
        $context = $this->createContext(function () {
            usleep(1e6);
        });

        $context->start();

        $this->assertRunTimeLessThan([$context, 'kill'], 1000);

        $this->assertFalse($context->isRunning());
    }

    public function testStartWhileRunningThrowsError(): void
    {
        $context = $this->createContext(function () {
            usleep(100);
        });

        $context->start();

        $this->expectException(StatusError::class);
        $context->start();
    }

    public function testStartMultipleTimesThrowsError(): void
    {
        $this->expectException(StatusError::class);

        $this->assertRunTimeGreaterThan(function () {
            Loop::run(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                $context->join();

                $context->start();
                $context->join();
            });
        }, 2000);
    }

    public function testExceptionInContextPanics(): void
    {
        $this->expectException(PanicError::class);

        $context = $this->createContext(function () {
            throw new \Exception('Exception in fork.');
        });

        $context->start();
        $context->join();
    }

    public function testReturnUnserializableDataPanics(): void
    {
        $this->expectException(PanicError::class);

        $context = $this->createContext(function () {
            return function () {
                // do nothing
            };
        });

        $context->start();
        $context->join();
    }

    public function testJoinWaitsForChild(): void
    {
        $this->assertRunTimeGreaterThan(function () {
            Loop::run(function () {
                $context = $this->createContext(function () {
                    sleep(1);
                });

                $context->start();
                $context->join();
            });
        }, 1000);
    }

    public function testJoinWithoutStartThrowsError(): void
    {
        $context = $this->createContext(function () {
            usleep(100);
        });

        $this->expectException(StatusError::class);
        $context->join();
    }

    public function testJoinResolvesWithContextReturn(): void
    {
        $context = $this->createContext(function () {
            return 42;
        });

        $context->start();
        $this->assertSame(42, $context->join());
    }

    public function testSendAndReceive(): void
    {
        $context = $this->createContext(function (Channel $channel) {
            $channel->send(1);

            return $channel->receive();
        });

        $value = 42;

        $context->start();
        $this->assertSame(1, $context->receive());

        $context->send($value);
        $this->assertSame($value, $context->join());
    }

    /**
     * @depends testSendAndReceive
     */
    public function testJoinWhenContextSendingData(): void
    {
        $this->expectException(SynchronizationError::class);

        $context = $this->createContext(function (Channel $channel) {
            yield $channel->send(0);

            return 42;
        });

        $context->start();
        $context->join();
    }

    /**
     * @depends testSendAndReceive
     */
    public function testReceiveBeforeContextHasStarted(): void
    {
        $context = $this->createContext(function (Channel $channel) {
            yield $channel->send(0);
            return 42;
        });

        $this->expectException(StatusError::class);
        $context->receive();
    }

    /**
     * @depends testSendAndReceive
     */
    public function testSendBeforeContextHasStarted(): void
    {
        $context = $this->createContext(function (Channel $channel) {
            $channel->send(0);

            return 42;
        });

        $this->expectException(StatusError::class);
        $context->send(0);
    }

    /**
     * @depends testSendAndReceive
     */
    public function testReceiveWhenContextHasReturned(): void
    {
        $context = $this->createContext(function (Channel $channel) {
            $channel->send(0);

            return 42;
        });

        $context->start();

        $this->expectException(SynchronizationError::class);

        $context->receive();
        $context->receive();
        $context->join();
    }

    /**
     * @depends testSendAndReceive
     */
    public function testSendExitResult(): void
    {
        $this->expectException(\Error::class);

        $context = $this->createContext(function (Channel $channel) {
            $channel->receive();

            return 42;
        });

        $context->start();
        $context->send(new ExitSuccess(0));
        $context->join();
    }

    public function testExitingContextOnJoin(): void
    {
        $this->expectException(ContextException::class);
        $this->expectExceptionMessage('The context stopped responding');

        $context = $this->createContext(function () {
            exit;
        });

        $context->start();
        $context->join();
    }

    public function testExitingContextOnReceive(): void
    {
        $this->expectException(ChannelException::class);
        $this->expectExceptionMessage('The channel closed unexpectedly');

        $context = $this->createContext(function () {
            exit;
        });

        $context->start();
        $context->receive();
    }

    public function testExitingContextOnSend(): void
    {
        $this->expectException(ChannelException::class);
        $this->expectExceptionMessage('Sending on the channel failed');

        $context = $this->createContext(function () {
            exit;
        });

        $context->start();
        $context->send(\str_pad("", 1024 * 1024, "-"));
    }
}
