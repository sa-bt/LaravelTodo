<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Task;
use App\Models\User;



class Goal extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'type', 'parent_id', 'start_date', 'end_date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Goal::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Goal::class, 'parent_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
