<?php

declare(strict_types=1);

namespace MijnAfvalwijzer;

use JsonSerializable;

final readonly class Collection implements JsonSerializable
{
    public function __construct(
        public string $date,
        public string $type,
        public string $label,
        public ?string $registrationStatus = null,
    ) {
    }

    /** @return array{date: string, type: string, label: string, registrationStatus: ?string} */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
