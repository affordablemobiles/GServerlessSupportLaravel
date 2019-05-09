<?php

namespace A1comms\GaeSupportLaravel\Auth;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use A1comms\GaeSupportLaravel\Auth\Model\IAPUser;
use A1comms\GaeSupportLaravel\Auth\Guard\UsersAPIGuard;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::provider('null', function(Application $app, array $config) {
            if (!empty($config['model'])){
                return new NullUserProvider($config['model']);
            }

            return new NullUserProvider(IAPUser::class);
        });

        Auth::viaRequest('gae-users-api', [UsersAPIGuard::class, 'validate']);
    }
}