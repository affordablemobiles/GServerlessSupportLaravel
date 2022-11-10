<?php

declare(strict_types=1);

namespace A1comms\GaeSupportLaravel\Auth;

use A1comms\GaeSupportLaravel\Auth\Model\IAPUser;
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

        Auth::viaRequest('firebase', [Guard\Firebase_Guard::class, 'validate']);

        Auth::viaRequest('gae-internal', [Guard\AppEngine_Guard::class, 'validate']);
        Auth::viaRequest('gae-iap', [Guard\IAP_Guard::class, 'validate']);
        Auth::viaRequest('gae-oidc', [Guard\OIDC_Guard::class, 'validate']);
        Auth::viaRequest('gae-oauth2', [Guard\OAuth2_Guard::class, 'validate']);

        Auth::viaRequest('gae-combined-iap', [Guard\Combined\IAP_Guard::class, 'validate']);
        Auth::viaRequest('gae-combined-iap-oidc', [Guard\Combined\IAP_OIDC_Guard::class, 'validate']);
        Auth::viaRequest('gae-combined-iap-oidc-oauth2', [Guard\Combined\IAP_OIDC_OAuth2_Guard::class, 'validate']);
    }
}
