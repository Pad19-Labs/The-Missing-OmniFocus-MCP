# Third-party notices

The Missing OmniFocus MCP is MIT-licensed (see [LICENSE](LICENSE)). The
prebuilt binaries additionally redistribute the following third-party
software.

## PHP

This product includes PHP software, freely available from
<http://www.php.net/software/>.

The binaries embed a statically compiled PHP interpreter, © The PHP Group,
licensed under the [PHP License v3.01](https://www.php.net/license/3_01.txt).
This product is not called "PHP" and is not endorsed by The PHP Group.

The static interpreter is built with [static-php-cli](https://static-php.dev),
which publishes the sources and licenses of everything it links. The linked
libraries are permissively licensed (including OpenSSL — Apache-2.0,
SQLite — public domain, zlib, libxml2 — MIT, curl, oniguruma — BSD).

## PHP package dependencies

The application code bundled in the binaries depends on packages under the
MIT, BSD-3-Clause, and Apache-2.0 licenses — including Laravel and Symfony
components (MIT). Every package's full license text is included inside the
binary's `vendor/` directory, and the exact roster is visible in
[`bridge/composer.lock`](bridge/composer.lock) (or via `composer licenses`).

## Trademarks

OmniFocus is a trademark of The Omni Group. This project is not affiliated
with, sponsored, or endorsed by The Omni Group; it interoperates with
OmniFocus through its public Omni Automation API.
