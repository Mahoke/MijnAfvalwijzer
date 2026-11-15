<?php

declare(strict_types=1);

namespace MijnAfvalwijzer;

use Closure;
use InvalidArgumentException;
use Throwable;

final class Client
{
    /** @var Closure(string): mixed Runtime validation also protects against incorrectly typed callbacks. */
    private readonly Closure $httpGet;

    /**
     * Optional transport must return the HTML body for an HTTP 200 response,
     * and throw on network errors or any other HTTP status.
     * @param HttpClient|(callable(string): mixed)|null $httpGet The callback result is validated at runtime.
     */
    public function __construct(
        HttpClient|callable|null $httpGet = null,
        int $timeoutSeconds = 30,
    ) {
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('Timeout must be at least one second.');
        }
        $httpGet ??= new CurlHttpClient($timeoutSeconds);
        $this->httpGet = $httpGet instanceof HttpClient
            ? $httpGet->get(...)
            : Closure::fromCallable($httpGet);
    }

    /** @return list<Collection> */
    public function collections(string $postcode, string|int $houseNumber, string $addition = ''): array
    {
        $url = (new Address($postcode, $houseNumber, $addition))->url();
        try {
            $html = ($this->httpGet)($url);
            if (!is_string($html)) {
                throw new ScraperException('HTTP transport must return an HTML string.');
            }
        } catch (ScraperException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new ScraperException('HTTP transport failed.', 0, $error);
        }

        return (new HtmlParser())->parse($html);
    }
}
