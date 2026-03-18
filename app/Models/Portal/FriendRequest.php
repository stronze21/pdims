<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class FriendRequest extends Model
{
    protected $connection = 'portal';
    protected $table = 'friend_requests';

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'status',
    ];

    public function fromUser()
    {
        return $this->belongsTo(PortalUserAccount::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(PortalUserAccount::class, 'to_user_id');
    }
}
