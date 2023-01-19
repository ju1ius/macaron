# ju1ius/macaron

*«Macaron le Glouton»* is the french name of the Cookie Monster.

`ju1ius/macaron` provides a cookie-aware wrapper for `symfony/http-client`.

## Installation

```sh
composer require ju1ius/macaron
```

## Usage

```php
use ju1ius\Macaron\CookieAwareHttpClient;
use ju1ius\Macaron\CookieJar;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpClient\HttpClient;

$macaron = new CookieAwareHttpClient(HttpClient::create());
$cookieJar = CookieJar::of([
    'foo' => 'bar',
]);
$macaron->request('GET', 'https://example.com', [
    'extra' => ['cookies' => $cookieJar],
]);
```
