<?php

declare(strict_types=1);

namespace MijnAfvalwijzer\Tests;

use InvalidArgumentException;
use MijnAfvalwijzer\Client;
use MijnAfvalwijzer\HttpClient;
use MijnAfvalwijzer\ScraperException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClientTest extends TestCase
{
    public function testRejectsInvalidTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client(timeoutSeconds: 0);
    }

    public function testForwardsAddressAndReturnsSerializableCollections(): void
    {
        $url = null;
        $client = new Client(httpGet: static function (string $requested) use (&$url): string {
            $url = $requested;
            return file_get_contents(__DIR__ . '/fixtures/calendar-addition.html');
        });

        $result = $client->collections('1234CD', 2, 'd');

        self::assertSame('https://www.mijnafvalwijzer.nl/nl/1234CD/2/d/', $url);
        self::assertCount(91, $result);
        $serialized = json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($result[0]->date, $serialized[0]['date']);
        self::assertSame($result[0]->type, $serialized[0]['type']);
        self::assertSame($result[0]->label, $serialized[0]['label']);
        self::assertSame($result[0]->registrationStatus, $serialized[0]['registrationStatus']);
    }

    public function testPreservesOriginalTransportException(): void
    {
        $cause = new RuntimeException('Network unavailable');
        $client = new Client(httpGet: static fn () => throw $cause);
        try {
            $client->collections('1234AB', 4);
            self::fail('Expected transport exception.');
        } catch (ScraperException $error) {
            self::assertSame($cause, $error->getPrevious());
        }
    }

    public function testAcceptsHttpClientAdapter(): void
    {
        $transport = $this->createMock(HttpClient::class);
        $transport->expects(self::once())->method('get')
            ->with('https://www.mijnafvalwijzer.nl/nl/1234CD/2/d/')
            ->willReturn(file_get_contents(__DIR__ . '/fixtures/calendar-addition.html'));

        self::assertCount(91, (new Client(httpGet: $transport))->collections('1234CD', 2, 'd'));
    }

    public function testInvalidAddressDoesNotMakeRequest(): void
    {
        $transport = $this->createMock(HttpClient::class);
        $transport->expects(self::never())->method('get');
        $this->expectException(InvalidArgumentException::class);

        (new Client(httpGet: $transport))->collections('invalid', 2);
    }

    public function testPreservesScraperException(): void
    {
        $cause = new ScraperException('Unexpected HTTP status: 429');
        $client = new Client(httpGet: static fn () => throw $cause);

        try {
            $client->collections('1234AB', 4);
            self::fail('Expected transport exception.');
        } catch (ScraperException $error) {
            self::assertSame($cause, $error);
        }
    }

    public function testRejectsNonStringTransportResult(): void
    {
        $this->expectException(ScraperException::class);
        $this->expectExceptionMessage('HTTP transport must return an HTML string.');
        (new Client(httpGet: static fn () => null))->collections('1234AB', 4);
    }
}
