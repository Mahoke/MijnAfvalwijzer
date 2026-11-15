<?php

declare(strict_types=1);

namespace MijnAfvalwijzer;

use Countable;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class MijnAfvalwijzer implements Countable
{
    private readonly Address $address;
    private readonly Client $client;

    public function __construct(
        string $postcode,
        string|int $houseNumber,
        string $addition = '',
        ?Client $client = null,
    ) {
        $this->address = new Address($postcode, $houseNumber, $addition);
        $this->client = $client ?? new Client();
    }

    /** @return list<Collection> All dates present on the website, including past dates. */
    public function all(): array
    {
        return $this->client->collections(
            $this->address->postcode,
            $this->address->houseNumber,
            $this->address->addition,
        );
    }

    /** @return list<Collection> */
    public function year(int $year): array
    {
        if ($year < 1 || $year > 9999) {
            throw new InvalidArgumentException('Year must be between 1 and 9999.');
        }

        $prefix = sprintf('%04d-', $year);

        return array_values(array_filter(
            $this->all(),
            static fn (Collection $collection): bool => str_starts_with($collection->date, $prefix),
        ));
    }

    /** @return list<int> Available years in ascending order. */
    public function years(): array
    {
        return array_values(array_unique(array_map(
            static fn (Collection $collection): int => (int) substr($collection->date, 0, 4),
            $this->all(),
        )));
    }

    /** @return list<Collection> Includes the given day, or today, in Europe/Amsterdam. */
    public function upcoming(?DateTimeImmutable $from = null): array
    {
        $timezone = new DateTimeZone('Europe/Amsterdam');
        $date = ($from ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone)->format('Y-m-d');

        return array_values(array_filter(
            $this->all(),
            static fn (Collection $collection): bool => $collection->date >= $date,
        ));
    }

    /** First upcoming collection, optionally restricted to a waste type code. */
    public function next(?string $type = null, ?DateTimeImmutable $from = null): ?Collection
    {
        foreach ($this->upcoming($from) as $collection) {
            if ($type === null || $collection->type === $type) {
                return $collection;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->all());
    }
}
