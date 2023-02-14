# ju1ius/macaron

[![codecov](https://codecov.io/gh/ju1ius/macaron/branch/main/graph/badge.svg?token=ZggiPVHfWa)](https://codecov.io/gh/ju1ius/macaron)

*«Macaron le Glouton»* is the French name of the Cookie Monster, and a
[RFC6265bis](https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html)-compliant
cookie jar implementation for PHP HTTP clients.

## Installation

```sh
composer require ju1ius/macaron
```


## Usage

Currently, the only available integration is for the `symfony/http-client` component.
Integration with other PSR18-compatible clients is planned.

* [symfony/http-client integration](docs/symfony.md)
