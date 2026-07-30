<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Goal extends Model
{
    /**
     * How much a task counts towards a completion percentage.
     *
     * A task carries no weight of its own: it inherits the priority of its
     * goal. The three levels already existed and were purely decorative, so
     * nothing new has to be entered anywhere for a percentage to become
     * weighted.
     *
     * Only percentages use this. Counts stay counts everywhere, otherwise a
     * day with five tasks would report nine of them.
     */
    public const PRIORITY_WEIGHTS = ['low' => 1, 'medium' => 2, 'high' => 3];

    /** Weight of a goal whose priority is missing or unknown. */
    public const DEFAULT_WEIGHT = 2;

    protected $fillable = [
        'user_id', 'title', 'description', 'parent_id', 'priority', 'status',
        'send_task_reminder', 'reminder_time'
    ];

    protected $appends = ['stats'];

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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('day');
    }

    // ------------------------------------
    // Weight
    // ------------------------------------

    /**
     * Weight of a single priority value.
     */
    public static function weightOf(?string $priority): int
    {
        return self::PRIORITY_WEIGHTS[$priority] ?? self::DEFAULT_WEIGHT;
    }

    /**
     * The same map as a SQL expression, for aggregates that must not load models.
     *
     * Both the keys and the values come from the constant above, never from
     * request input, so building the statement by hand is safe here.
     */
    public static function weightSql(string $column = 'goals.priority'): string
    {
        $cases = '';

        foreach (self::PRIORITY_WEIGHTS as $priority => $weight) {
            $cases .= " WHEN '{$priority}' THEN {$weight}";
        }

        return "CASE {$column}{$cases} ELSE " . self::DEFAULT_WEIGHT . ' END';
    }

    // ------------------------------------
    // Accessors / Calculated Attributes
    // ------------------------------------

    protected function stats(): Attribute
    {
        return Attribute::make(
            get: function () {
                // اگر والد است، آمار ندارد
                if ($this->relationLoaded('children') ? $this->children->isNotEmpty() : $this->children()->exists()) {
                    return null;
                }

                $today = now()->toDateString();

                // گرفتن تسک‌ها تا امروز
                $tasks = $this->relationLoaded('tasks')
                    ? $this->tasks->where('day', '<=', $today)->values()
                    : $this->tasks()->where('day', '<=', $today)->orderBy('day')->get();

                if ($tasks->isEmpty()) {
                    return null;
                }

                $total = $tasks->count();
                $done  = $tasks->where('is_done', true)->count();

                // ─── متغیرهای Streak ───
                $maxSuccessStreak = ['length' => 0, 'start' => null, 'end' => null];
                $currentSuccessStreak = 0;
                $successStreakStart = null;

                $maxFailStreak = ['length' => 0, 'start' => null, 'end' => null];
                $currentFailStreak = 0;
                $failStreakStart = null;

                // ✅ برای محاسبه current streak (آخرین وضعیت)
                $lastSuccessStreak = 0;
                $lastFailStreak = 0;
                $lastStatus = null; // 'success' یا 'fail'

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

                        // ✅ ذخیره آخرین وضعیت
                        $lastStatus = 'success';
                        $lastSuccessStreak = $currentSuccessStreak;
                        $lastFailStreak = 0;
                    } else {
                        $currentFailStreak++;
                        if ($currentFailStreak === 1) {
                            $failStreakStart = $date;
                        }
                        // reset success
                        $currentSuccessStreak = 0;
                        $successStreakStart = null;

                        // ✅ ذخیره آخرین وضعیت
                        $lastStatus = 'fail';
                        $lastFailStreak = $currentFailStreak;
                        $lastSuccessStreak = 0;
                    }

                    // max streak update
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

                // ─── ساخت خروجی ───
                return [
                    'total' => $total,
                    'done'  => $done,

                    // ✅ Current Streak (وضعیت فعلی)
                    'current_streak_success' => [
                        'length' => $lastStatus === 'success' ? $lastSuccessStreak : 0,
                    ],
                    'current_streak_fail' => [
                        'length' => $lastStatus === 'fail' ? $lastFailStreak : 0,
                    ],

                    // Max Streak (رکورد)
                    'max_streak_success' => [
                        'length' => $maxSuccessStreak['length'],
                        'start'  => $maxSuccessStreak['start'] ? Carbon::parse($maxSuccessStreak['start'])->toDateString() : null,
                        'end'    => $maxSuccessStreak['end']   ? Carbon::parse($maxSuccessStreak['end'])->toDateString()   : null,
                    ],
                    'max_streak_fail' => [
                        'length' => $maxFailStreak['length'],
                        'start'  => $maxFailStreak['start'] ? Carbon::parse($maxFailStreak['start'])->toDateString() : null,
                        'end'    => $maxFailStreak['end']   ? Carbon::parse($maxFailStreak['end'])->toDateString()   : null,
                    ],
                ];
            }
        )->shouldCache();
    }
}
