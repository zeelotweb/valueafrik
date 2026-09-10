<?php

namespace App\Concerns;

use App\Models\Hashtag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasHashtags
{
    public function hashtags(): MorphToMany
    {
        return $this->morphToMany(Hashtag::class, 'taggable', 'hashtag_taggable');
    }
}
