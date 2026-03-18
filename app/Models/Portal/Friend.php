<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class Friend extends Model
{
    protected $connection = 'portal';
    protected $table = 'friends';

    protected $fillable = [
        'user_id',
        'friend_user_id',
    ];

    public function user()
    {
        return $this->belongsTo(PortalUserAccount::class, 'user_id');
    }

    public function friendUser()
    {
        return $this->belongsTo(PortalUserAccount::class, 'friend_user_id');
    }
}
