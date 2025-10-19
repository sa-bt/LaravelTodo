<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Goal extends Model
{
    protected $fillable = [
        'user_id', 'title', 'description', 'parent_id', 'priority', 'status', 'send_task_reminder', 'reminder_time'
    ];

    protected $appends = [
        'stats',
    ];

    protected $casts = [
        'send_task_reminder' => 'boolean',
        'reminder_time' => 'datetime',

    ];

    // ------------------------------------
    // Relations
    // ------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Goal::class, 'parent_id');
    }

    /**
     * مرتب سازی بر اساس فیلد 'day' که در جدول tasks وجود دارد.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('day');
    }


    // ------------------------------------
    // Accessors / Calculated Attributes
    // ------------------------------------

    /**
     * Calculate and return Goal statistics, including total tasks, done tasks, and streaks.
     */
// داخل app/Models/Goal.php

    protected function stats(): Attribute
    {
        return Attribute::make(
            get: function () {
                // اگر این هدف، والد است (فرزند دارد)، آمار محاسبه نشود
                if ($this->relationLoaded('children') ? $this->children->isNotEmpty() : $this->children()->exists()) {
                    return null;
                }

                $today = now()->toDateString();

                // اگر tasks قبلاً eager-load شده، همان را استفاده کن؛ وگرنه از DB بگیر تا امروز
                $tasks = $this->relationLoaded('tasks')
                    ? $this->tasks->where('day', '<=', $today)->values()
                    : $this->tasks()->where('day', '<=', $today)->orderBy('day')->get();

                if ($tasks->isEmpty()) {
                    return null;
                }

                $total = $tasks->count();
                $done  = $tasks->where('is_done', true)->count();

                $maxSuccessStreak = ['length' => 0, 'start' => null, 'end' => null];
                $currentSuccessStreak = 0;
                $successStreakStart = null;

                $maxFailStreak = ['length' => 0, 'start' => null, 'end' => null];
                $currentFailStreak = 0;
                $failStreakStart = null;

                foreach ($tasks as $task) {
                    $date = $task->day;

                    if ($task->is_done) {
                        $currentSuccessStreak++;
                        if ($currentSuccessStreak === 1) {
                            $successStreakStart = $date;
                        }
                        // reset fail
                        $currentFailStreak = 0;
                        $failStreakStart = null;
                    } else {
                        $currentFailStreak++;
                        if ($currentFailStreak === 1) {
                            $failStreakStart = $date;
                        }
                        // reset success
                        $currentSuccessStreak = 0;
                        $successStreakStart = null;
                    }

                    if ($currentSuccessStreak >= $maxSuccessStreak['length']) {
                        $maxSuccessStreak = [
                            'length' => $currentSuccessStreak,
                            'start'  => $successStreakStart,
                            'end'    => $date,
                        ];
                    }

                    if ($currentFailStreak >= $maxFailStreak['length']) {
                        $maxFailStreak = [
                            'length' => $currentFailStreak,
                            'start'  => $failStreakStart,
                            'end'    => $date,
                        ];
                    }
                }

                return [
                    'total' => $total,
                    'done'  => $done,
                    'max_streak_success' => [
                        'length' => $maxSuccessStreak['length'],
                        'start'  => $maxSuccessStreak['start'] ? \Carbon\Carbon::parse($maxSuccessStreak['start'])->toDateString() : null,
                        'end'    => $maxSuccessStreak['end']   ? \Carbon\Carbon::parse($maxSuccessStreak['end'])->toDateString()   : null,
                    ],
                    'max_streak_fail' => [
                        'length' => $maxFailStreak['length'],
                        'start'  => $maxFailStreak['start'] ? \Carbon\Carbon::parse($maxFailStreak['start'])->toDateString() : null,
                        'end'    => $maxFailStreak['end']   ? \Carbon\Carbon::parse($maxFailStreak['end'])->toDateString()   : null,
                    ],
                ];
            }
        )->shouldCache();
    }
}
