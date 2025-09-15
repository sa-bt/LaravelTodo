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
    public function toArray(Request $request)
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'is_done'      => $this->is_done,
            'goal_id'    => $this->goal_id,
            'goal_title'    => $this->goal->title,
            'day'  => $this->day
                ? Jalalian::fromCarbon(Carbon::parse($this->day))->format('Y-m-d')
                : null,
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}
