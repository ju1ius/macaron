# souplette/macaron

[![codecov](https://codecov.io/github/souplette-php/macaron/branch/main/graph/badge.svg?token=AUvl8W7oKb)](https://codecov.io/github/souplette-php/macaron)

*«Macaron le Glouton»* is the French name of the Cookie Monster, and a
[RFC6265bis](https://httpwg.org/http-extensions/draft-ietf-httpbis-rfc6265bis.html)-compliant
cookie jar implementation for PHP HTTP clients.

## Installation

```sh
composer require souplette/macaron
```


## Usage

Currently, the only available integration is for the `symfony/http-client` component.
Integration with other PSR18-compatible clients is planned.

* [symfony/http-client integration](docs/symfony.md)
