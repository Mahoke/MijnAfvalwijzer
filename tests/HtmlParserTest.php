<?php

declare(strict_types=1);

namespace MijnAfvalwijzer\Tests;

use MijnAfvalwijzer\HtmlParser;
use MijnAfvalwijzer\ScraperException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlParserTest extends TestCase
{
    #[DataProvider('calendars')]
    public function testParsesRecordedCalendar(string $fixture, int $expectedCount, string $knownDate): void
    {
        $items = (new HtmlParser())->parse(file_get_contents(__DIR__ . '/fixtures/' . $fixture));
        self::assertCount($expectedCount, $items);

        $keys = array_map(static fn ($item) => $item->date . ':' . $item->type, $items);
        $sorted = $keys;
        sort($sorted);
        self::assertSame($sorted, $keys, 'Chronological order');
        self::assertSame($keys, array_values(array_unique($keys)), 'No responsive-layout duplicates');

        $rest = array_values(array_filter($items, static fn ($item) => $item->date === $knownDate && $item->type === 'restafval'));
        self::assertCount(1, $rest);
        self::assertSame('Restafval', $rest[0]->label);
        self::assertNotEmpty(array_filter($items, static fn ($item) => $item->registrationStatus === 'Afgemeld'));
    }

    public static function calendars(): iterable
    {
        yield 'base address' => ['calendar.html', 90, '2026-11-16'];
        yield 'with addition' => ['calendar-addition.html', 91, '2026-11-17'];
    }

    public function testDeduplicatesWhileRetainingDifferentYears(): void
    {
        $items = (new HtmlParser())->parse(self::calendar() . self::calendar() . self::calendar('woensdag 17 november', '2027'));
        self::assertCount(2, $items);
        self::assertSame('2027-11-17', $items[1]->date);
    }

    public function testRetainsDifferentTypesOnSameDay(): void
    {
        self::assertCount(2, (new HtmlParser())->parse(self::calendar() . self::calendar(type: 'gft')));
    }

    public function testAcceptsLeapDay(): void
    {
        self::assertSame('2024-02-29', (new HtmlParser())->parse(self::calendar('donderdag 29 februari', '2024'))[0]->date);
    }

    public function testNormalizesNonbreakingSpaces(): void
    {
        self::assertSame('2026-11-16', (new HtmlParser())->parse(self::calendar('maandag&nbsp;16&nbsp;november'))[0]->date);
    }

    public function testPreservesUtf8AndDecodesEntities(): void
    {
        $html = str_replace('Restafval', 'Papier &amp; café', self::calendar());
        self::assertSame('Papier & café', (new HtmlParser())->parse($html)[0]->label);
    }

    #[DataProvider('invalidCalendars')]
    public function testRejectsInvalidCalendar(string $html): void
    {
        $this->expectException(ScraperException::class);
        (new HtmlParser())->parse($html);
    }

    public static function invalidCalendars(): iterable
    {
        yield 'invalid leap day' => [self::calendar('vrijdag 29 februari', '2025')];
        yield 'unrecognized date' => [self::calendar('morgen')];
        yield 'missing year' => [self::calendar(year: 'unknown')];
        yield 'missing type' => [self::calendar(type: '')];
        yield 'conflicting duplicates' => [self::calendar() . str_replace('Restafval', 'Conflicting label', self::calendar())];
        yield 'empty response' => [''];
        yield 'address selection page' => ['<html><body>Kies adres</body></html>'];
    }

    public function testRestoresLibxmlErrorMode(): void
    {
        $previous = libxml_use_internal_errors(false);
        try {
            (new HtmlParser())->parse(self::calendar());
            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    private static function calendar(string $date = 'maandag 16 november', string $year = '2026', string $type = 'restafval'): string
    {
        return '<div class="month-section" id="november-' . $year . '"><div><div>'
            . '<a href="#waste-' . $type . '" title="Restafval"><p>'
            . '<span class="span-line-break">' . $date . '</span>'
            . '<span class="afvaldescr">Restafval</span></p></a></div></div></div>';
    }
}
