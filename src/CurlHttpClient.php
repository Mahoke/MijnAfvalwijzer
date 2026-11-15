<?php

declare(strict_types=1);

namespace MijnAfvalwijzer;

use InvalidArgumentException;

final readonly class CurlHttpClient implements HttpClient
{
    public function __construct(private int $timeoutSeconds = 30)
    {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('Timeout must be at least one second.');
        }
    }

    public function get(string $url): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new ScraperException('Unable to initialize cURL.');
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->timeoutSeconds),
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'mahoke-mijnafvalwijzer/0.1 (+https://github.com/Mahoke/MijnAfvalwijzer)',
                CURLOPT_HTTPHEADER => ['Accept: text/html'],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $html = curl_exec($curl);
            if (!is_string($html)) {
                throw new ScraperException('HTTP request failed: ' . curl_error($curl));
            }

            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($status !== 200) {
                throw new ScraperException('Unexpected HTTP status: ' . $status);
            }

            return $html;
        } finally {
            unset($curl);
        }
    }
}
