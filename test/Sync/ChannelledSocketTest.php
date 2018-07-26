<?php

namespace Amp\Parallel\Test\Sync;

use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledSocket;
use Amp\PHPUnit\TestCase;
use Concurrent\Task;

class ChannelledSocketTest extends TestCase
{
    /**
     * @return resource[]
     */
    protected function createSockets(): array
    {
        if (($sockets = @\stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)) === false) {
            $message = "Failed to create socket pair";
            if ($error = \error_get_last()) {
                $message .= \sprintf(" Errno: %d; %s", $error["type"], $error["message"]);
            }
            $this->fail($message);
        }
        return $sockets;
    }

    public function testSendReceive(): void
    {
        [$left, $right] = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $b = new ChannelledSocket($right, $right);

        $message = 'hello';

        $a->send($message);
        $data = $b->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testSendReceiveLongData(): void
    {
        [$left, $right] = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $b = new ChannelledSocket($right, $right);

        $length = 0xffff;
        $message = '';
        for ($i = 0; $i < $length; ++$i) {
            $message .= \chr(\random_int(0, 255));
        }

        Task::async(function () use ($a, $message) {
            $a->send($message);
        });

        $data = $b->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testInvalidDataReceived(): void
    {
        [$left, $right] = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $b = new ChannelledSocket($right, $right);

        \fwrite($left, \pack('L', 10) . '1234567890');

        $this->expectException(ChannelException::class);
        $b->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendUnserializableData(): void
    {
        [$left] = $this->createSockets();
        $a = new ChannelledSocket($left, $left);

        $this->expectException(ChannelException::class);
        $a->send(function () {
            // do nothing
        });
    }

    /**
     * @depends testSendReceive
     */
    public function testSendAfterClose(): void
    {
        [$left] = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $a->close();

        $this->expectException(ChannelException::class);
        $a->send('hello');
    }

    /**
     * @depends testSendReceive
     */
    public function testReceiveAfterClose(): void
    {
        [$left] = $this->createSockets();
        $a = new ChannelledSocket($left, $left);
        $a->close();

        $this->expectException(ChannelException::class);
        $a->receive();
    }
}
