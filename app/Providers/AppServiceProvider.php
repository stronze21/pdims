<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // SendChatPushNotification is auto-discovered by Laravel via its handle() type-hint.
        // Do NOT manually register it here — that causes duplicate event firing.

        App::singleton('chargetable', function () {
            return array(
                'DRUMA',
                'DRUMB',
                'DRUMC',
                'DRUME',
                'DRUMK',
                'DRUMAA',
                'DRUMAB',
                'DRUMR',
                'DRUMS',
                'DRUMAD',
                'DRUMAE',
                'DRUMAF',
                'DRUMAG',
                'DRUMAH',
                'DRUMAI',
                'DRUMAJ',
                'DRUMAK',
                'DRUMAL',
                'DRUMAM',
                'DRUMAN',
                'DRUMAO',
            );
        });

        Gate::after(function ($user, $ability) {
            return $user->hasRole('Super Admin'); // note this returns boolean
        });
    }
}
