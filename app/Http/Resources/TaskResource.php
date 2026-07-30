<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Morilog\Jalali\Jalalian;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $day = $this->day ? Carbon::parse($this->day) : null;

        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'is_done'    => (bool) $this->is_done,
            'goal_id'    => $this->goal_id,
            'goal_title' => $this->whenLoaded('goal', fn () => $this->goal->title),

            // Sent ready to use so the client never has to know the map from
            // priority to weight. One source of truth, on the server.
            'weight'     => $this->weight,

            // برای API، match با تقویم، ساخت/آپدیت و همه منطق‌های فرانت
            'day' => $day
                ? $day->toDateString()
                : null,

            // فقط برای نمایش شمسی، اگر جایی لازم شد
            'day_shamsi' => $day
                ? Jalalian::fromCarbon($day)->format('Y-m-d')
                : null,

            'created_at' => $this->created_at
                ? $this->created_at->toDateTimeString()
                : null,
        ];
    }
}
