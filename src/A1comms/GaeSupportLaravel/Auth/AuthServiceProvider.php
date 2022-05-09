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

        Auth::provider('null', function (Application $app, array $config) {
            if (!empty($config['model'])) {
                return new NullUserProvider($config['model']);
            }

            return new NullUserProvider(IAPUser::class);
        });

        Auth::provider('list', function (Application $app, array $config) {
            if (empty($config['list'])) {
                $config['list'] = [];
            }

            if (!empty($config['model'])) {
                return new ListUserProvider($config['model'], $config['list']);
            }

            return new ListUserProvider(IAPUser::class, $config['list']);
        });

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
