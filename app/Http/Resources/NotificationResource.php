<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Illuminate\Notifications\DatabaseNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id'        => (string) $this->id,
            'type'      => $data['type'] ?? 'generic',
            'title'     => $data['title'] ?? 'اعلان',
            'body'      => $data['body'] ?? null,
            'url'       => $data['url'] ?? '/',
            'icon'      => $data['icon'] ?? '/icons/notification.png',
            'tag'       => $data['tag'] ?? null,
            'meta'      => $data['meta'] ?? (is_array($data) ? $data : []),

            'read_at'   => optional($this->read_at)?->toISOString(),
            'created_at'=> optional($this->created_at)?->toISOString(),
            'updated_at'=> optional($this->updated_at)?->toISOString(),
        ];
    }
}
