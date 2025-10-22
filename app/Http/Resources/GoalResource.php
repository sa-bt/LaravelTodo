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

            'send_task_reminder' => $this->send_task_reminder,
            'reminder_time'      => optional($this->reminder_time)->format('H:i'),

            'children_count'=> $this->when(isset($this->children_count), $this->children_count),

            'stats' => $this->stats ?? [
                    'total' => 0,
                    'done' => 0,
                    'max_streak_success' => ['length' => 0, 'start' => null, 'end' => null],
                    'max_streak_fail'    => ['length' => 0, 'start' => null, 'end' => null],
                ],
        ];
    }
}
