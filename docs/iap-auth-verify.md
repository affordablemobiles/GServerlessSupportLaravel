## IAP (Identity Aware Proxy) Authentication into Laravel

When IAP is turned on, you need a way to validate it's presence for the session, plus register the session with Laravel's user system.

For this, we validate each request statelessly, using the request headers (from the remnents of the Users API).

### Usage

**1.** Register the service provider in `config/app.php`:

```php
    'providers' => [
        AffordableMobiles\GServerlessSupportLaravel\Auth\AuthServiceProvider::class,
    ];
```

**2.** Switch to our handler in `config/auth.php`:

```php
    'guards' => [
        'web' => [
            'driver' => 'gae-combined-iap',
        ],

        'api' => [
            'driver' => 'gae-combined-iap',
        ],
    ],
```

**3.** Add the expected audience (from "Signed Header JWT Audience" in Cloud Console, under IAP) to the environment.

Usually done via a line in `.env`, e.g. :

```bash
IAP_AUDIENCE="/projects/<project_id>/apps/<project_name>"
```

**4.** Then to enable it for all requests (rather than via request middleware), add this in `app/Http/Kernel.php`:

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

**5.** The default action for failed authentication is to redirect to a guest accessible login page, but that won't work here.

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

**6.** To demonstrate our ability to view the signed in user's email, try adding this in `routes/web.php`:

```php
Route::get('/user/email', function () {
    return Auth::id();
});
```

### Custom User Model

By default, the user object returned is an instance of `AffordableMobiles\GServerlessSupportLaravel\Auth\Model\IAPUser`.

To use your own custom user model, you can setup a "null" provider.

To enable it, add the following to your `config/auth.php` file:

```php
    'defaults' => [
        'provider' => 'null'
    ],

    'providers' => [
        'null' => [
            'driver' => 'null',
            'model' => App\User::class,
        ],
    ],
```

where `App\User::class` is the model you'd like to use.

The "null" provider will not do any lookups, queries or validation, it simply returns a new instance of the model filled with user information.

It will try to use the `fill` function to insert a value into the field returned by `getAuthIdentifierName` function, which in Eloquent is the name of the primary key field.

So long as you don't try to save the `User` model, you should be able to use an Eloquent model and even join it to other models, without needing a database table and remaining fully stateless (except of course any records you create joined to our virtual `User` record).

An example modified `App\User` model can be found below:

```php
<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use AffordableMobiles\GServerlessSupportLaravel\Auth\Contracts\NullUserModel;

class User extends Authenticatable implements NullUserModel
{
    use Notifiable;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'email';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'varchar';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
}
```