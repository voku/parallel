<?php

namespace Amp\Parallel\Sync;

use Amp\Deferred;
use Amp\Loop;
use Amp\Parallel\Context\ContextException;
use Amp\Promise;
use Amp\Socket\Socket;
use Amp\Socket\ResourceSocket;
use Amp\TimeoutCancellationToken;
use Amp\TimeoutException;
use function Amp\call;
use function Amp\Socket\connect;

class IpcHub
{
    const PROCESS_START_TIMEOUT = 5000;
    const CONNECT_TIMEOUT = 1000;
    const KEY_RECEIVE_TIMEOUT = 1000;

    /** @var int */
    private $keyLength;

    /** @var resource|null */
    private $server;

    /** @var string|null */
    private $uri;

    /** @var int[] */
    private $keys;

    /** @var string|null */
    private $watcher;

    /** @var Deferred[] */
    private $acceptor = [];

    /** @var string|null */
    private $toUnlink;

    /**
     * @param string $uri IPC server URI.
     * @param string $key Key for this connection attempt.
     *
     * @return Promise<Socket>
     */
    public static function connect(string $uri, string $key): Promise
    {
        return call(function () use ($uri, $key): \Generator {
            try {
                $socket = yield connect($uri, null, new TimeoutCancellationToken(self::CONNECT_TIMEOUT));
            } catch (\Throwable $exception) {
                throw new \RuntimeException("Could not connect to IPC socket", 0, $exception);
            }

            \assert($socket instanceof Socket);

            yield $socket->write($key);

            return $socket;
        });
    }

    public function __construct(int $keyLength = 32)
    {
        if ($keyLength <= 0) {
            throw new \Error('The IPC key length must be greater than 0');
        }

        $this->keyLength = $keyLength;

        $isWindows = \strncasecmp(\PHP_OS, "WIN", 3) === 0;

        if ($isWindows) {
            $this->uri = "tcp://127.0.0.1:0";
        } else {
            $suffix = \bin2hex(\random_bytes(10));
            $path = \sys_get_temp_dir() . "/amp-cluster-ipc-" . $suffix . ".sock";
            $this->uri = "unix://" . $path;
            $this->toUnlink = $path;
        }

        $this->server = \stream_socket_server($this->uri, $errno, $errstr, \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN);

        if (!$this->server) {
            throw new \RuntimeException(\sprintf("Could not create IPC server: (Errno: %d) %s", $errno, $errstr));
        }

        if ($isWindows) {
            $name = \stream_socket_get_name($this->server, false);
            $port = \substr($name, \strrpos($name, ":") + 1);
            $this->uri = "tcp://127.0.0.1:" . $port;
        }

        $keys = &$this->keys;
        $acceptor = &$this->acceptor;
        $this->watcher = Loop::onReadable($this->server, static function (string $watcher, $server) use (&$keys, &$acceptor, $keyLength): void {
            // Error reporting suppressed since stream_socket_accept() emits E_WARNING on client accept failure.
            if (!$socket = @\stream_socket_accept($server, 0)) {  // Timeout of 0 to be non-blocking.
                return; // Accepting client failed.
            }

            $delay = Loop::delay(self::KEY_RECEIVE_TIMEOUT, function () use (&$read, $socket): void {
                Loop::cancel($read);
                \fclose($socket);
            });

            $read = Loop::onReadable($socket, function (string $watcher, $socket) use (&$acceptor, &$keys, $keyLength, $delay): void {
                static $key = "";

                // Error reporting suppressed since fread() emits E_WARNING if reading fails.
                $chunk = @\fread($socket, $keyLength - \strlen($key));

                if ($chunk === false) {
                    Loop::cancel($delay);
                    Loop::cancel($watcher);
                    \fclose($socket);
                    return;
                }

                $key .= $chunk;

                if (\strlen($key) < $keyLength) {
                    return; // Entire key not received, await readability again.
                }

                Loop::cancel($delay);
                Loop::cancel($watcher);

                if (!isset($keys[$key])) {
                    \fclose($socket);
                    return; // Ignore possible foreign connection attempt.
                }

                $pid = $keys[$key];

                \assert(isset($acceptor[$pid]), 'Invalid PID in process key list');

                $deferred = $acceptor[$pid];
                unset($acceptor[$pid], $keys[$key]);
                $deferred->resolve(ResourceSocket::fromServerSocket($socket));
            });
        });

        Loop::disable($this->watcher);
    }

    public function __destruct()
    {
        Loop::cancel($this->watcher);
        \fclose($this->server);
        if ($this->toUnlink !== null) {
            @\unlink($this->toUnlink);
        }
    }

    /**
     * @return string The IPC server URI.
     */
    final public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @param int $id Generate a key that will be used for the given identifier.
     *
     * @return string Key that the process should be used with {@see IpcHub::connect()} in the other process.
     */
    final public function generateKey(int $id): string
    {
        $key = \random_bytes($this->keyLength);
        $this->keys[$key] = $id;
        return $key;
    }

    /**
     * @param int $id Wait for this ID to connect.
     *
     * @return Promise<Socket>
     */
    final public function accept(int $id): Promise
    {
        return call(function () use ($id): \Generator {
            $this->acceptor[$id] = new Deferred;

            Loop::enable($this->watcher);

            try {
                $socket = yield Promise\timeout($this->acceptor[$id]->promise(), self::PROCESS_START_TIMEOUT);
            } catch (TimeoutException $exception) {
                $key = \array_search($id, $this->keys, true);
                \assert(\is_string($key), "Key for {$id} not found");
                unset($this->acceptor[$id], $this->keys[$key]);
                throw new ContextException("Starting the process timed out", 0, $exception);
            } finally {
                if (empty($this->acceptor)) {
                    Loop::disable($this->watcher);
                }
            }

            return $socket;
        });
    }
}
