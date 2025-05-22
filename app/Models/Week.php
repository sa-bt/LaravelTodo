<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Week extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'result',
        'color',
    ];

    /**
     * The goals that are assigned to this week.
     */
    public function goals()
    {
        return $this->belongsToMany(Goal::class, 'goal_weeks')
            ->withPivot('status', 'note')
            ->withTimestamps();
    }
}
