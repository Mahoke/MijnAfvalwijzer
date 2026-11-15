<?php

declare(strict_types=1);

namespace MijnAfvalwijzer;

use InvalidArgumentException;

final readonly class Address
{
    public string $postcode;
    public string $houseNumber;
    public string $addition;

    public function __construct(string $postcode, string|int $houseNumber, string $addition = '')
    {
        $postcode = strtoupper(str_replace(' ', '', trim($postcode)));
        $houseNumber = (string) $houseNumber;
        $addition = trim($addition);

        if (!preg_match('/\A[1-9][0-9]{3}[A-Z]{2}\z/', $postcode)) {
            throw new InvalidArgumentException('Expected a Dutch postcode, e.g. 1234CD.');
        }
        if (!preg_match('/\A[1-9][0-9]*\z/', $houseNumber)) {
            throw new InvalidArgumentException('House number must be a positive integer; pass additions separately.');
        }
        if ($addition !== '' && !preg_match('/\A[A-Za-z0-9][A-Za-z0-9 -]*\z/', $addition)) {
            throw new InvalidArgumentException('Addition may contain letters, digits, spaces and hyphens.');
        }

        $this->postcode = $postcode;
        $this->houseNumber = $houseNumber;
        $this->addition = $addition;
    }

    public function url(): string
    {
        return 'https://www.mijnafvalwijzer.nl/nl/' . $this->postcode . '/'
            . $this->houseNumber . '/'
            . ($this->addition === '' ? '' : rawurlencode($this->addition) . '/');
    }
}
