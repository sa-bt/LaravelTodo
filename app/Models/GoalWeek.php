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
}
