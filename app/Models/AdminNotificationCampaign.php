<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotificationCampaign extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'url',
        'channel',
        'audience',
        'recipient_count',
    ];

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
