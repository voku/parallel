<?php

namespace Amp\Parallel\Test\Sync;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\ByteStream\StreamException;
use Amp\Parallel\Sync\ChannelException;
use Amp\Parallel\Sync\ChannelledStream;
use Amp\PHPUnit\TestCase;

class ChannelledStreamTest extends TestCase
{
    /**
     * @return InputStream|OutputStream
     */
    protected function createMockStream()
    {
        return new class implements InputStream, OutputStream
        {
            private $buffer = "";

            public function read(): ?string
            {
                $data = $this->buffer;
                $this->buffer = "";
                return $data;
            }

            public function write(string $data): void
            {
                $this->buffer .= $data;
            }

            public function end(string $finalData = ""): void
            {
                throw new \BadMethodCallException("Error in line " . __LINE__);
            }

            public function close()
            {
                throw new \BadMethodCallException("Error in line " . __LINE__);
            }
        };
    }

    public function testSendReceive(): void
    {
        $mock = $this->createMockStream();
        $a = new ChannelledStream($mock, $mock);
        $b = new ChannelledStream($mock, $mock);

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
        $mock = $this->createMockStream();
        $a = new ChannelledStream($mock, $mock);
        $b = new ChannelledStream($mock, $mock);

        $length = 0xffff;
        $message = '';
        for ($i = 0; $i < $length; ++$i) {
            $message .= \chr(\random_int(0, 255));
        }

        $a->send($message);
        $data = $b->receive();
        $this->assertSame($message, $data);
    }

    /**
     * @depends testSendReceive
     */
    public function testInvalidDataReceived(): void
    {
        $mock = $this->createMockStream();
        $b = new ChannelledStream($mock, $mock);

        $mock->write(pack('L', 10) . '1234567890');

        $this->expectException(ChannelException::class);
        $b->receive();
    }

    /**
     * @depends testSendReceive
     */
    public function testSendUnserializableData(): void
    {
        $mock = $this->createMockStream();
        $a = new ChannelledStream($mock, $mock);

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
        $mock = $this->createMock(OutputStream::class);
        $mock->expects($this->once())
            ->method('write')
            ->will($this->throwException(new StreamException));

        $a = new ChannelledStream($this->createMock(InputStream::class), $mock);

        $this->expectException(ChannelException::class);
        $a->send('hello');
    }

    /**
     * @depends testSendReceive
     */
    public function testReceiveAfterClose(): void
    {
        $mock = $this->createMock(InputStream::class);
        $mock->expects($this->once())
            ->method('read')
            ->willReturn(null);

        $a = new ChannelledStream($mock, $this->createMock(OutputStream::class));

        $this->expectException(ChannelException::class);
        $a->receive();
    }
}
