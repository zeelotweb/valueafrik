<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'user_id',
        'disk',
        'path',
        'thumbnail_path',
        'mime_type',
        'type',
        'size',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * The small inline-display derivative — feed grids, chat bubbles,
     * dashboard cards should all use this instead of url(), which is
     * reserved for the lightbox. Falls back to the full image for rows
     * that predate thumbnails, or for passthrough types (GIF/SVG) that
     * never get one.
     */
    public function thumbnailUrl(): string
    {
        return $this->thumbnail_path
            ? Storage::disk($this->disk)->url($this->thumbnail_path)
            : $this->url();
    }
}
