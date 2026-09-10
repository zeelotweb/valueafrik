<?php

namespace App\Concerns;

use App\Models\Mention;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMentions
{
    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }
}
