<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Morilog\Jalali\Jalalian;

class Task extends Model
{
    protected $fillable = [
        'goal_id', 'title', 'day', 'is_done'
    ];

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * Share of this task in a completion percentage, inherited from its goal.
     *
     * Every caller that reads it already loads the goal relation, so this
     * costs no extra query. Aggregates never come through here; they use the
     * SQL form of the same map.
     */
    protected function weight(): Attribute
    {
        return Attribute::get(fn () => Goal::weightOf($this->goal?->priority));
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
