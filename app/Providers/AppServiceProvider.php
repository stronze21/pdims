<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Events\Portal\NewChatMessage;
use App\Events\Portal\NewPatientChatMessage;
use App\Listeners\SendChatPushNotification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
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

        Event::listen(NewChatMessage::class, SendChatPushNotification::class);
        Event::listen(NewPatientChatMessage::class, SendChatPushNotification::class);

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
            );
        });

        Gate::after(function ($user, $ability) {
            return $user->hasRole('Super Admin'); // note this returns boolean
        });
    }
}
