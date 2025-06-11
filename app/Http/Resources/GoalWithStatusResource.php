<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalWithStatusResource extends JsonResource
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
        'title' => $this->title,
        'description' => $this->description,
        'priority' => $this->priority,
        'status' => $this->pivot->status,
        'note' => $this->pivot->note,
    ];
    }
}
