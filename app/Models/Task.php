<?php

namespace App\Models;

use Carbon\Carbon;
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
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
