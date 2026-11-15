# Contributing

Use PHP 8.2+ with curl, dom, libxml, mbstring, xml and xmlwriter.

```sh
composer install
composer check
```

`composer check` runs Composer validation, PHPStan, PHPUnit and PHP-CS-Fixer.
Use `composer format` to fix formatting, or `composer format:check` to check it.
GitHub Actions runs all three tools on PHP 8.2, 8.3, 8.4 and 8.5.

Include a small fixture and regression test with calendar parsing fixes. Do not
commit full address pages, personal data, cookies or API keys. Tests must work
offline; transport tests use a temporary local PHP server.

Keep the library framework-independent. Application-specific caching and scheduling
belong in the calling application. Use four spaces, LF, UTF-8 and
`declare(strict_types=1)` in PHP files.

Describe the problem, resulting behavior and validation in pull requests. Use
fictional addresses in new tests. Existing calendar fixtures contain only limited
calendar fragments from the supplied reference project.

## Releases

1. Run `composer check`.
2. Inspect the distribution using
   `composer archive --format=zip --dir=<temporary-directory>`.
3. Push the reviewed code and a SemVer tag, such as `v0.1.0`.
4. For the first release, submit `https://github.com/Mahoke/MijnAfvalwijzer` to
   [Packagist](https://packagist.org/packages/submit). Verify ownership of the
   vendor name and enable automatic GitHub updates.

Do not add a `version` field to `composer.json`; versions come from Git tags.
