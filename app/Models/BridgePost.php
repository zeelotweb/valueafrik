<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BridgePost extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'theme',
        'initiator_id',
        'partner_id',
        'status',
        'initiator_body',
        'partner_body',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
        ];
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function sideFor(User $user): ?string
    {
        return match ($user->id) {
            $this->initiator_id => 'initiator',
            $this->partner_id => 'partner',
            default => null,
        };
    }

    public function isComplete(): bool
    {
        return filled($this->initiator_body) && filled($this->partner_body);
    }
}
