<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;

class PushNotificationSubscription extends Model
{
    protected $connection = 'portal';
    protected $table = 'push_notification_subscriptions';

    protected $fillable = [
        'user_id',
        'ntfy_topic',
        'platform',
    ];

    public function user()
    {
        return $this->belongsTo(PortalUserAccount::class, 'user_id');
    }
}
