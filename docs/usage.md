# Advanced usage

## Upcoming collections

The main entry point is an address-bound `MijnAfvalwijzer` instance:

```php
$calendar = new MijnAfvalwijzer\MijnAfvalwijzer('1234AB', 10, 'A');
$upcoming = $calendar->upcoming();
$nextGft = $calendar->next('gft');

// A specific date, inclusive. Instants are converted to Europe/Amsterdam.
$from = new DateTimeImmutable('2027-01-01', new DateTimeZone('Europe/Amsterdam'));
$upcoming = $calendar->upcoming($from);
$next = $calendar->next(from: $from);
```

`all()`, `year()`, `years()` and `count()` each fetch the current calendar.
`count($calendar)` also works. `year()` returns an empty list for an unavailable
year; `next()` returns `null` if no matching collection remains.
Only years already published on the calendar page are available.

## Requests

Each call to `all()`, `year()`, `years()`, `upcoming()`, `next()` or `count()`
performs one HTTP request. Filters apply to the returned calendar locally;
they do not fetch additional years or pages. There is no built-in caching,
polling or automatic retry. Manage caching in your application if needed.

## HTTP transport

The default cURL transport uses TLS verification, a 30-second total timeout and
a connection timeout of at most 10 seconds. Only HTTP 200 is accepted; redirects
are not followed.

```php
$client = new MijnAfvalwijzer\Client(timeoutSeconds: 15);
$calendar = new MijnAfvalwijzer\MijnAfvalwijzer('1234AB', 10, client: $client);
```

To use an existing HTTP client, supply a callback returning the HTML body.
The callback must handle HTTP errors and timeouts itself:

```php
$client = new MijnAfvalwijzer\Client(httpGet: static function (string $url) use ($httpClient): string {
    $response = $httpClient->request('GET', $url, ['timeout' => 15]);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Unexpected HTTP response');
    }

    return $response->getContent();
});
```

Alternatively, implement `MijnAfvalwijzer\HttpClient::get(string $url): string`
and pass the adapter as `httpGet`. `timeoutSeconds` only applies to the default
transport. The declared cURL extension requirement also applies when using a
custom transport.

`Client::collections($postcode, $houseNumber, $addition)` remains available for
stateless, lower-level requests and returns every available date without caching.

## Parsing saved HTML

```php
$collections = (new MijnAfvalwijzer\HtmlParser())->parse($html);
```

An empty or unrecognized calendar throws `ScraperException`: an unknown address,
an unavailable calendar and changed markup cannot be distinguished reliably.
Transport exceptions are preserved through `getPrevious()`.

The house number addition is forwarded to the website. The library cannot verify
whether the website distinguishes or ignores it. Registration badges are returned
as text; they are not interpreted as booking confirmations.
