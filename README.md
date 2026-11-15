# MijnAfvalwijzer

Fetch waste collection dates from [MijnAfvalwijzer](https://www.mijnafvalwijzer.nl/) with PHP.
Framework-independent, with no external runtime dependencies.

## Installation

Requires PHP 8.2+ and the `curl`, `dom` and `libxml` extensions.

```sh
composer require mahoke/mijn-afvalwijzer
```

## Usage

```php
use MijnAfvalwijzer\MijnAfvalwijzer;

$calendar = new MijnAfvalwijzer('1234AB', 10); // Optional third argument: house number addition.

$collections = $calendar->upcoming();

foreach ($collections as $collection) {
    echo "{$collection->date}: {$collection->label}\n";
}
```

The example postcode is fictional. Replace it with your own address.

| Method | Returns |
| --- | --- |
| `all()` | All available collection dates |
| `year(2027)` | Collections in the given year |
| `years()` | Available years |
| `upcoming()` | Collections from today onwards, including today |
| `next()` / `next('gft')` | Next collection, or `null` |
| `count()` | Total number of available collections |

Each method fetches the current calendar with one HTTP request. There is no
built-in caching. Available years depend on what the website publishes.

Results are sorted by date and deduplicated. Each `Collection` has readonly
properties and can be serialized with `json_encode()`:

| Property | Description |
| --- | --- |
| `date` | Local collection date (`YYYY-MM-DD`) |
| `type` | Waste type code, e.g. `gft` or `restafval` |
| `label` | Dutch description |
| `registrationStatus` | Status shown on the website, or `null` |

`all()` includes past dates; `upcoming()` starts today. A listed collection does
not confirm a pickup registration. Invalid addresses throw `InvalidArgumentException`;
request failures and unrecognized calendars throw `MijnAfvalwijzer\ScraperException`.

The client reads the public HTML calendar. Cache results in your application;
collection dates and the website's markup can change.
See [advanced usage](docs/usage.md) for filtering and custom HTTP clients.

## Development

```sh
composer install
composer check
```

Runs Composer validation, PHPStan, PHPUnit and PHP-CS-Fixer.
Use `composer format` to apply formatting fixes.

[Contributing](CONTRIBUTING.md) · [MIT license](LICENSE)

An independent project, not affiliated with MijnAfvalwijzer.
