<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth;

use A1comms\GaeSupportLaravel\Auth\Model\IAPUser;
use Illuminate\Auth\RequestGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Auth::provider('null', fn (Application $app, array $config) => new NullUserProvider($config['model'] ?? IAPUser::class));

        Auth::provider('list', fn (Application $app, array $config) => new ListUserProvider($config['model'] ?? IAPUser::class, $config['list'] ?? []));

        Auth::provider('group', fn (Application $app, array $config) => new GroupUserProvider($config['model'] ?? IAPUser::class, $config['group'] ?? ''));

        $this->viaRequest('firebase', [Guard\Firebase_Guard::class, 'validate']);

        $this->viaRequest('gae-internal', [Guard\AppEngine_Guard::class, 'validate']);
        $this->viaRequest('gae-iap', [Guard\IAP_Guard::class, 'validate']);
        $this->viaRequest('gae-oidc', [Guard\OIDC_Guard::class, 'validate']);
        $this->viaRequest('gae-oauth2', [Guard\OAuth2_Guard::class, 'validate']);

        $this->viaRequest('gae-combined-iap', [Guard\Combined\IAP_Guard::class, 'validate']);
        $this->viaRequest('gae-combined-iap-oidc', [Guard\Combined\IAP_OIDC_Guard::class, 'validate']);
        $this->viaRequest('gae-combined-iap-oidc-oauth2', [Guard\Combined\IAP_OIDC_OAuth2_Guard::class, 'validate']);
    }

    /**
     * Register a new callback based request guard.
     *
     * @param string $driver
     *
     * @return $this
     */
    public function viaRequest($driver, callable $callback)
    {
        return Auth::extend($driver, function ($app, $name, $config) use ($callback) {
            $guard = new RequestGuard($callback, $app['request'], Auth::createUserProvider($config['provider'] ?? null));

            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
    }
}
