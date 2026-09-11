<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionCollaborator extends Model
{
    protected $fillable = [
        'live_session_id',
        'user_id',
        'requested_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
