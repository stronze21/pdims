<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\App;
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
            );
        });
    }
}
