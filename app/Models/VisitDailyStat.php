<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class VisitDailyStat extends Model
{
    protected $fillable = [
        'date',
        'views',
        'unique_visitors',
        'sessions',
        'bounced_sessions',
        'guest_views',
        'member_views',
        'active_members',
        'new_visitors',
        'avg_session_seconds',
        'top_paths',
        'referrer_groups',
        'device_types',
        'browsers',
        'hourly',
    ];

    protected $casts = [
        'top_paths' => 'array',
        'referrer_groups' => 'array',
        'device_types' => 'array',
        'browsers' => 'array',
        'hourly' => 'array',
    ];

    /**
     * The day is written as a bare date and read back as a date object.
     *
     * The plain date cast would store it with a midnight time attached, which
     * MySQL silently trims and SQLite keeps. The nightly roll up looks a day up
     * by its date before writing it, so on SQLite that lookup would miss every
     * time and the second run of a day would collide instead of updating.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->startOfDay(),
            set: fn ($value) => Carbon::parse($value)->toDateString(),
        );
    }
}
