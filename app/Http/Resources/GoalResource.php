<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'parent_id'    => $this->parent_id,
            'parent_title'    => $this->parent ? $this->parent->title : null,
            'created_at'  => $this->created_at->toDateTimeString(),
            'children' => GoalResource::collection($this->whenLoaded('children')),
            'stats' => $this->stats ?? [
                'total' => 0,
                'done' => 0,
                'max_streak_success' => ['length' => 0, 'start' => null, 'end' => null],
                'max_streak_fail'    => ['length' => 0, 'start' => null, 'end' => null],
            ],
        ];
    }
}
