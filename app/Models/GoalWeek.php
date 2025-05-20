<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoalWeek extends Model
{
    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    public function week()
    {
        return $this->belongsTo(Week::class);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isLost(): bool
    {
        return $this->status === 'lose';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    protected static function booted()
    {
        static::saved(function ($goalWeek) {
            $week = $goalWeek->week;
            $result = \App\Services\WeekService::calculateResult($week);
            $week->update(['result' => $result]);
        });

        static::deleted(function ($goalWeek) {
            $week = $goalWeek->week;
            $result = \App\Services\WeekService::calculateResult($week);
            $week->update(['result' => $result]);
        });
    }
}
