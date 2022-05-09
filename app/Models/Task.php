<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $guarded=[];

    const DONE_STATUS='done';
    const DOING_STATUS='doing';

    public static $statuses=[
      self::DONE_STATUS,
      self::DOING_STATUS
    ];
}
