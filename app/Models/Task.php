<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory,SoftDeletes;

    protected $guarded=[];

    const DONE_STATUS='done';
    const DOING_STATUS='doing';

    public static $statuses=[
      self::DONE_STATUS,
      self::DOING_STATUS
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
