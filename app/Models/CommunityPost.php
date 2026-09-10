<?php

namespace App\Models;

use App\Concerns\HasBookmarks;
use App\Concerns\HasComments;
use App\Concerns\HasHashtags;
use App\Concerns\HasMentions;
use App\Concerns\HasReactions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityPost extends Model
{
    use HasBookmarks, HasComments, HasHashtags, HasMentions, HasReactions, SoftDeletes;

    protected $fillable = [
        'community_id',
        'user_id',
        'body',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function url(): string
    {
        return route('communities.show', $this->community);
    }
}
