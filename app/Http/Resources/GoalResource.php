<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'status'        => $this->status,
            'priority'      => $this->priority,
            'parent_id'     => $this->parent_id,
            'parent_title'  => $this->whenLoaded('parent', fn() => optional($this->parent)->title),
            'created_at'    => $this->created_at->toDateTimeString(),

            // به جای count() در لحظه، از ستون آماده استفاده می‌کنیم:
            'children_count'=> $this->when(isset($this->children_count), $this->children_count),

            // اگر مدل مقدار stats دارد، همان را بده؛ وگرنه مقدار تهیِ استاندارد
            'stats' => $this->stats ?? [
                    'total' => 0,
                    'done' => 0,
                    'max_streak_success' => ['length' => 0, 'start' => null, 'end' => null],
                    'max_streak_fail'    => ['length' => 0, 'start' => null, 'end' => null],
                ],
        ];
    }
}
