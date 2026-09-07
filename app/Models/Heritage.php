<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Heritage extends Model
{
    /**
     * The six world regions heritages are grouped into — see HeritageSeeder.
     * Culture Sprint's "region I'm curious about" filter offers exactly
     * this list.
     *
     * @var list<string>
     */
    public const REGIONS = ['Africa', 'Asia', 'Middle East', 'Europe', 'Americas', 'Oceania'];

    protected $fillable = [
        'name',
        'slug',
        'region',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
