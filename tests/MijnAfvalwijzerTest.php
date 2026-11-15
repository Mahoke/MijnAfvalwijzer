<?php

declare(strict_types=1);

namespace MijnAfvalwijzer\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use MijnAfvalwijzer\Client;
use MijnAfvalwijzer\MijnAfvalwijzer;
use MijnAfvalwijzer\ScraperException;
use PHPUnit\Framework\TestCase;

final class MijnAfvalwijzerTest extends TestCase
{
    private int $requests = 0;
    private string $requestedUrl = '';

    public function testFetchesCalendarForEachHelper(): void
    {
        $calendar = $this->calendar();
        self::assertSame(0, $this->requests);
        self::assertCount(4, $calendar->all());
        self::assertSame('https://www.mijnafvalwijzer.nl/nl/1234AB/10/A/', $this->requestedUrl);
        self::assertCount(2, $calendar->year(2026));
        self::assertSame([2026, 2027], $calendar->years());
        self::assertCount(4, $calendar);
        $calendar->upcoming(new DateTimeImmutable('2026-12-31T12:00:00+01:00'));
        $calendar->next();
        self::assertSame(6, $this->requests);
    }

    public function testFiltersAvailableYears(): void
    {
        $calendar = $this->calendar();
        $items = $calendar->year(2027);
        self::assertSame(['2027-01-01', '2027-01-04'], array_column($items, 'date'));
        self::assertSame([], $calendar->year(2028));
        self::assertSame(2, $this->requests);
    }

    public function testRejectsInvalidYearBeforeFetching(): void
    {
        $calendar = $this->calendar();
        try {
            $calendar->year(0);
            self::fail('Expected an invalid year error.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $this->requests);
        }
    }

    public function testUpcomingIncludesTodayAndUsesAmsterdamTimezone(): void
    {
        $calendar = $this->calendar();
        // This instant falls on January 1 in Amsterdam.
        $from = new DateTimeImmutable('2026-12-31T23:30:00+00:00');
        self::assertSame(['2027-01-01', '2027-01-04'], array_column($calendar->upcoming($from), 'date'));
        self::assertSame('2026-12-31', $from->format('Y-m-d'));
    }

    public function testNextSupportsWasteTypesAndExhaustedCalendars(): void
    {
        $calendar = $this->calendar();
        $from = new DateTimeImmutable('2026-12-31T12:00:00+01:00');
        self::assertSame('2026-12-31', $calendar->next(from: $from)?->date);
        self::assertSame('2027-01-04', $calendar->next('gft', $from)?->date);
        self::assertNull($calendar->next('unknown', $from));
        self::assertNull($calendar->next(from: new DateTimeImmutable('2028-01-01')));
        self::assertSame([], $calendar->upcoming(new DateTimeImmutable('2028-01-01')));
    }

    public function testEachCallFetchesFreshData(): void
    {
        $requests = 0;
        $calendar = new MijnAfvalwijzer('1234AB', 10, client: new Client(
            httpGet: static function () use (&$requests): string {
                return self::card(++$requests === 1 ? 'gft' : 'papier', 'maandag 04 januari', 2027);
            },
        ));

        self::assertSame('gft', $calendar->all()[0]->type);
        self::assertSame('papier', $calendar->all()[0]->type);
        self::assertSame('papier', $calendar->all()[0]->type);
        self::assertSame(3, $requests);
    }

    public function testAllowsRetryAfterTransportFailure(): void
    {
        $requests = 0;
        $calendar = new MijnAfvalwijzer('1234AB', 10, client: new Client(
            httpGet: static function () use (&$requests): string {
                if (++$requests === 1) {
                    throw new ScraperException('Temporary failure');
                }

                return self::card('gft', 'maandag 04 januari', 2027);
            },
        ));
        try {
            $calendar->all();
            self::fail('Expected a transport error.');
        } catch (ScraperException) {
            self::assertCount(1, $calendar->all());
            self::assertSame(2, $requests);
        }
    }

    private function calendar(): MijnAfvalwijzer
    {
        return new MijnAfvalwijzer('1234AB', 10, 'A', new Client(httpGet: function (string $url): string {
            ++$this->requests;
            $this->requestedUrl = $url;

            // Intentionally unsorted input across a year boundary.
            return self::card('gft', 'maandag 04 januari', 2027)
                . self::card('restafval', 'donderdag 31 december', 2026)
                . self::card('papier', 'vrijdag 01 januari', 2027)
                . self::card('gft', 'maandag 28 december', 2026);
        }));
    }

    private static function card(string $type, string $date, int $year): string
    {
        return '<div class="month-section" id="month-' . $year . '"><div><div>'
            . '<a href="#waste-' . $type . '" title="' . $type . '"><p>'
            . '<span class="span-line-break">' . $date . '</span>'
            . '<span class="afvaldescr">' . $type . '</span></p></a></div></div></div>';
    }
}
