# Symfony HttpClient integration

## Client instantiation

The `MacaronHttpClient` class decorates an existing symfony `HttpClient` instance
to add RFC6265-bis compliant cookie management:

```php
use Souplette\Macaron\Bridge\Symfony\MacaronHttpClient;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
$macaron = new MacaronHttpClient($client, new UriService());
```

As-is, the macaron client won't do anything more
than delegate the request to the decorated client.
In order to actually process cookies,
you need to pass a cookie jar factory to the request "extra" options:

```php
use Souplette\Macaron\Bridge\Symfony\MacaronHttpClient;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\HttpClient\HttpClient;

$macaron = new MacaronHttpClient(HttpClient::create(), new UriService());
$factory = ...; // see next chapter
$macaron->request('GET', 'https://example.test', [
    'extra' => [
        MacaronHttpClient::OPTION_KEY => $factory,
    ],
]);
```

## Cookie jar factory option

The factory option can be one of the following types:
* `null` to disable cookie handling
* a `CookieJar` instance
* an array of `Cookie` objects
* an array where the keys are cookie names and values are cookie values
* a `callable` returning any of the 4 preceding types

### Passing a cookie jar

Passing an existing cookie jar allows sharing cookies between several requests:

```php
use Souplette\Macaron\Bridge\Symfony\MacaronHttpClient;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\HttpClient\HttpClient;

$macaron = new MacaronHttpClient(HttpClient::create(), new UriService());
$jar = new CookieJar();
$macaron->request('GET', 'https://example.test', [
    'extra' => [
        MacaronHttpClient::OPTION_KEY => $jar,
    ],
]);
```

### Passing an array

For the simplest use-cases, you can pass an array of scalars:

```php
use Souplette\Macaron\Bridge\Symfony\MacaronHttpClient;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\HttpClient\HttpClient;

$macaron = new MacaronHttpClient(HttpClient::create(), new UriService());
$macaron->request('GET', 'https://www.example.test/foo/bar', [
    'extra' => [
        MacaronHttpClient::OPTION_KEY => [
            'foo' => 'bar',
        ],
    ],
]);
```

The previous example creates a temporary cookie jar containing a non-persistent,
http-only cookie named `foo`, with value `bar` and bound to the request URI.
This acts as if the cookie jar had previously received the following set-cookie header:

```http request
Set-Cookie: foo=bar; Domain=www.example.test; Path=/foo; HttpOnly;
```

If you need more control on the cookie attributes,
you can pass an array of cookie objects:

```php
use Souplette\Macaron\Bridge\Symfony\MacaronHttpClient;
use Souplette\Macaron\Cookie;
use Souplette\Macaron\Cookie\SameSite;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\HttpClient\HttpClient;

$macaron = new MacaronHttpClient(HttpClient::create(), new UriService());
$macaron->request('GET', 'https://www.example.test/foo/bar', [
    'extra' => [
        MacaronHttpClient::OPTION_KEY => [
            new Cookie('foo', 'bar', 'example.test', '/', secureOnly: true),
            new Cookie('baz', 'qux', 'example.test', '/', sameSite: SameSite::Strict),
        ],
    ],
]);
```

### Passing a callback

Passing a callable allows deciding which cookie jar instance
to use depending on the request:

```php
use Psr\Http\Message\UriInterface;
use Souplette\Macaron\Bridge\Symfony\MacaronHttpClient;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Http\HttpMethod;
use Souplette\Macaron\Uri\UriService;
use Symfony\Component\HttpClient\HttpClient;

$macaron = new MacaronHttpClient(HttpClient::create(), new UriService());
$factory = function(HttpMethod $method, UriInterface $uri, array $options): ?CookieJar {
    // return a CookieJar or null to disable cookie management
};
$macaron->request('GET', 'https://www.example.test/foo/bar', [
    'extra' => [
        MacaronHttpClient::OPTION_KEY => $factory,
    ],
]);
```
