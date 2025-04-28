<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'goal_id', 'title', 'description', 'is_done'
    ];

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }
}
