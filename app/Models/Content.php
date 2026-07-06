<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Content extends Model
{
    public const TYPE_NOTE = 'note';
    public const TYPE_QUOTE = 'quote';
    public const TYPE_EXPERIENCE = 'experience';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'content_type',
        'status',
        'source',
        'metadata',
        'published_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
