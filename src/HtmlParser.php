<?php

declare(strict_types=1);

namespace MijnAfvalwijzer;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class HtmlParser
{
    private const MONTHS = [
        'januari' => 1, 'februari' => 2, 'maart' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'augustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'december' => 12,
    ];

    /** @return list<Collection> */
    public function parse(string $html): array
    {
        if (trim($html) === '') {
            throw new ScraperException('Empty HTML response.');
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET)) {
                throw new ScraperException('Unable to parse calendar HTML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($dom);
        $collections = [];
        foreach ($xpath->query('//*[' . self::hasClass('month-section') . ']') as $section) {
            if (!$section instanceof DOMElement) {
                throw new ScraperException('Invalid calendar section.');
            }
            if (!preg_match('/-(\d{4})\z/', $section->getAttribute('id'), $yearMatch)) {
                throw new ScraperException('Calendar section is missing its year.');
            }
            $year = (int) $yearMatch[1];
            foreach ($xpath->query('.//a[starts-with(@href, "#waste-")]', $section) as $link) {
                if (!$link instanceof DOMElement) {
                    throw new ScraperException('Invalid collection card.');
                }
                $text = self::normalize($xpath->evaluate(
                    'string(.//span[' . self::hasClass('span-line-break') . '])',
                    $link,
                ));
                if (!preg_match('/\A\p{L}+\s+(\d{1,2})\s+([a-z]+)\z/u', $text, $match)) {
                    throw new ScraperException('Unrecognized collection date: ' . $text);
                }
                $day = (int) $match[1];
                $month = self::MONTHS[$match[2]] ?? 0;
                if (!checkdate($month, $day, $year)) {
                    throw new ScraperException('Invalid collection date: ' . $text);
                }

                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $type = substr($link->getAttribute('href'), strlen('#waste-'));
                $label = self::normalize($xpath->evaluate(
                    'string(.//span[' . self::hasClass('afvaldescr') . '])',
                    $link,
                ));
                if ($label === '') {
                    $label = self::normalize($link->getAttribute('title'));
                }
                if ($type === '' || $label === '') {
                    throw new ScraperException('Collection type or label is missing.');
                }

                // The status badge is a direct child of the collection card.
                $status = self::normalize($xpath->evaluate('string(../../span)', $link));
                $item = new Collection($date, $type, $label, $status === '' ? null : $status);
                $key = $date . ':' . $type;
                if (isset($collections[$key]) && $collections[$key] != $item) {
                    throw new ScraperException('Conflicting duplicate collection: ' . $key);
                }
                $collections[$key] = $item;
            }
        }

        if ($collections === []) {
            throw new ScraperException('No collections found. The address may be unknown, the calendar unavailable, or the HTML changed.');
        }
        ksort($collections);

        return array_values($collections);
    }

    private static function hasClass(string $class): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
    }

    private static function normalize(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }
}
