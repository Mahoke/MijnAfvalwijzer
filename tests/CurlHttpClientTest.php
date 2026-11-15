<?php

declare(strict_types=1);

namespace MijnAfvalwijzer\Tests;

use MijnAfvalwijzer\CurlHttpClient;
use MijnAfvalwijzer\ScraperException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CurlHttpClientTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        // Let the OS select a free port instead of using a fixed development port.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException($errorMessage, $errorCode);
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$baseUrl = 'http://' . $address;

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        self::$server = proc_open(
            [PHP_BINARY, '-S', $address, __DIR__ . '/fixtures/http-router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $nullDevice, 'a'], 2 => ['file', $nullDevice, 'a']],
            $pipes,
        );
        if (!is_resource(self::$server)) {
            throw new RuntimeException('Unable to start the local test server.');
        }
        fclose($pipes[0]);

        $deadline = microtime(true) + 5;
        do {
            $connection = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.1);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        self::tearDownAfterClass();
        throw new RuntimeException('Local test server did not become ready.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    public function testReturnsResponseBody(): void
    {
        self::assertSame('<html>Calendar</html>', (new CurlHttpClient())->get(self::$baseUrl . '/ok'));
    }

    #[DataProvider('httpErrors')]
    public function testRejectsHttpErrorsAndDoesNotFollowRedirects(string $path, int $status): void
    {
        $this->expectException(ScraperException::class);
        $this->expectExceptionMessage('Unexpected HTTP status: ' . $status);
        (new CurlHttpClient())->get(self::$baseUrl . $path);
    }

    public static function httpErrors(): iterable
    {
        yield 'not found' => ['/missing', 404];
        yield 'rate limited' => ['/rate-limited', 429];
        yield 'server error' => ['/error', 500];
        yield 'redirect to valid page' => ['/redirect', 302];
    }

    public function testReportsNetworkFailure(): void
    {
        // A reserved, non-listening port cannot accidentally contact another service.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message, STREAM_SERVER_BIND);
        try {
            $this->expectException(ScraperException::class);
            $this->expectExceptionMessage('HTTP request failed:');
            (new CurlHttpClient(timeoutSeconds: 1))->get('http://' . stream_socket_get_name($socket, false));
        } finally {
            fclose($socket);
        }
    }
}
