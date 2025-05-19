<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalWeekResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'goal_id' => $this->goal_id,
            'week_id' => $this->week_id,
            'status' => $this->status,
            'note' => $this->note,
            'goal' => new GoalResource($this->whenLoaded('goal')),
            'week' => new WeekResource($this->whenLoaded('week')),
        ];
    }
}
