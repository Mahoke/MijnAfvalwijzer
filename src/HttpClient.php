<?php

declare(strict_types=1);

namespace MijnAfvalwijzer;

interface HttpClient
{
    /**
     * Return the response body for HTTP 200; throw on any other status or network error.
     *
     * @throws ScraperException
     */
    public function get(string $url): string;
}
