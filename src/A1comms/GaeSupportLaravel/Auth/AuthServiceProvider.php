<?php

namespace A1comms\GaeSupportLaravel\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
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

        Auth::viaRequest('gae-users-api', [UsersAPIGuard::class, 'validate']);
    }
}