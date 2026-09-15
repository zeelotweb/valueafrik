<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunityReport extends Model
{
    protected $fillable = [
        'community_id',
        'reporter_id',
        'reason',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Files a report, unless this reporter already has an open one against
     * this exact target — without this, submitReport() could be called in
     * a loop to flood the admin queue with unlimited duplicate reports
     * against the same post/person. Silently no-ops on a duplicate rather
     * than erroring: the reporter's concern is already in the queue, so
     * the UI can still say "submitted" without revealing the dedup.
     */
    public static function file(User $reporter, Model $reportable, string $reason, ?Community $community = null): void
    {
        $alreadyOpen = self::query()
            ->where('reporter_id', $reporter->id)
            ->where('reportable_type', $reportable->getMorphClass())
            ->where('reportable_id', $reportable->getKey())
            ->where('status', 'open')
            ->exists();

        if ($alreadyOpen) {
            return;
        }

        $report = new self([
            'community_id' => $community?->id,
            'reporter_id' => $reporter->id,
            'reason' => $reason,
        ]);

        $report->reportable()->associate($reportable);
        $report->save();
    }
}
