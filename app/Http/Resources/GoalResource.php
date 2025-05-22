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
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}
