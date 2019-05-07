## IAP (Identity Aware Proxy) Authentication into Laravel

When IAP is turned on, you need a way to validate it's presence for the session, plus register the session with Laravel's user system.

For this, we validate each request statelessly, using the request headers (from the remnents of the Users API).

### Users API Headers

Google have confirmed in their documentation that it is perfectly valid to trust the Users API headers, that they are already performing IAP JWT token validation as part of their serving plane, so this allows us to quickly verify the presence of IAP & the user account that is logged in.

### Usage

Register the service provider in `config/app.php`:

```php
    'providers' => [
        A1comms\GaeSupportLaravel\Auth\AuthServiceProvider::class,
    ];
```

Switch to our handler in `config/auth.php`:

```php
    'guards' => [
        'web' => [
            'driver' => 'gae-users-api',
        ],

        'api' => [
            'driver' => 'gae-users-api',
        ],
    ],
```

Then to enable it for all requests (rather than via request middleware), add this in `app/Http/Kernel.php`:

```php
    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \Illuminate\Auth\Middleware\Authenticate::class,
            ...
        ],

        'api' => [
            \Illuminate\Auth\Middleware\Authenticate::class,
            ...
        ],
    ];
```

The default action for failed authentication is to redirect to a guest accessible login page, but that won't work here.

You'll probably want to add this into `app/Exceptions/Handler.php`:

```php
    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, \Illuminate\Auth\AuthenticationException $exception)
    {
        return $request->expectsJson()
                    ? response()->json(['message' => $exception->getMessage()], 401)
                    : response($exception->getMessage(), 401);
    }
```

To demonstrate our ability to view the signed in user's email, try adding this in `routes/web.php`:

```php
Route::get('/user/email', function () {
    return Auth::id();
});
```