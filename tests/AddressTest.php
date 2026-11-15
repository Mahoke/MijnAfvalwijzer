<?php

declare(strict_types=1);

namespace MijnAfvalwijzer\Tests;

use InvalidArgumentException;
use MijnAfvalwijzer\Address;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function testNormalizesPostcodeAndIncludesAddition(): void
    {
        self::assertSame('https://www.mijnafvalwijzer.nl/nl/1234CD/2/d/', (new Address(' 1234 cd ', 2, 'd'))->url());
    }

    public function testOmitsEmptyAddition(): void
    {
        self::assertSame('https://www.mijnafvalwijzer.nl/nl/1234AB/4/', (new Address('1234AB', '4'))->url());
    }

    public function testEncodesAddition(): void
    {
        self::assertStringEndsWith('/A%201/', (new Address('1234CD', 2, 'A 1'))->url());
    }

    #[DataProvider('invalidAddresses')]
    public function testRejectsInvalidAddress(string $postcode, string|int $number, string $addition): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Address($postcode, $number, $addition);
    }

    public static function invalidAddresses(): iterable
    {
        yield 'invalid postcode' => ['invalid', 2, ''];
        yield 'zero house number' => ['1234CD', 0, ''];
        yield 'addition in number' => ['1234CD', '2d', ''];
        yield 'path in addition' => ['1234CD', 2, '../'];
    }
}
