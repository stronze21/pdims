<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $connection = 'hospital';
    protected $table = 'hospital2.dbo.pharm_personal_access_tokens';
}
