<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Hashtag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function wallPosts(): MorphToMany
    {
        return $this->morphedByMany(WallPost::class, 'taggable', 'hashtag_taggable');
    }

    public function communityPosts(): MorphToMany
    {
        return $this->morphedByMany(CommunityPost::class, 'taggable', 'hashtag_taggable');
    }
}
