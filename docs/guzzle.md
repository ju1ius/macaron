# Guzzle integration

Macaron provides a middleware that can be used instead of
the default `cookies` middleware:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Souplette\Macaron\Bridge\Guzzle\MacaronMiddleware;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Uri\UriService;

// Create our macaron middleware:
$uriService = new UriService();
$cookieJar = new CookieJar($uriService);
$macaron = MacaronMiddleware::create($cookieJar, $uriService);
// Now we need to insert our middleware at the appropriate location:
$stack = HandlerStack::create();
// First, we remove the default guzzle `cookies` middleware:
$stack->remove('cookies');
// Then we insert our middleware after the `allow_redirects` middleware.
$stack->after('allow_redirects', $macaron, 'cookies');
// Finally we can create our client:
$client = new Client(['handler' => $stack]);
```

Since this setup is a bit verbose, you can use the following instead:

```php
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Souplette\Macaron\Bridge\Guzzle\MacaronMiddleware;
use Souplette\Macaron\CookieJar;
use Souplette\Macaron\Uri\UriService;

$stack = HandlerStack::create();
$uriService = new UriService();
$cookieJar = new CookieJar($uriService);
MacaronMiddleware::insert($stack, $cookieJar, $uriService);
$client = new Client(['handler' => $stack]);
```
