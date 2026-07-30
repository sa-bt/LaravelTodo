<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    /**
     * A view is written once and never touched again, so the updated column
     * would only ever repeat the created one.
     */
    public const UPDATED_AT = null;

    public const REFERRER_DIRECT = 'direct';
    public const REFERRER_SEARCH = 'search';
    public const REFERRER_SOCIAL = 'social';
    public const REFERRER_INTERNAL = 'internal';
    public const REFERRER_OTHER = 'other';

    public const DEVICE_MOBILE = 'mobile';
    public const DEVICE_TABLET = 'tablet';
    public const DEVICE_DESKTOP = 'desktop';

    protected $fillable = [
        'visitor_id',
        'session_id',
        'user_id',
        'path',
        'route_name',
        'is_guest',
        'referrer_host',
        'referrer_group',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'device_type',
        'browser',
        'platform',
        'is_bot',
        'created_at',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'is_bot' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every report starts here. Robot traffic is stored for diagnosis but must
     * never reach a number the admin reads as human visits.
     */
    public function scopeHuman(Builder $query): Builder
    {
        return $query->where('is_bot', false);
    }
}
