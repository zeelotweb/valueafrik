<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Every photo upload in the app (posts, messages, avatars, covers) funnels
 * through here so it lands on disk already shrunk and re-encoded, instead
 * of storing whatever multi-megabyte file a phone camera produced.
 */
class ImageOptimizer
{
    private const MAX_DIMENSION = 2048;

    private const QUALITY = 82;

    /**
     * Animated GIFs and SVGs pass through untouched — re-encoding a GIF to
     * WebP would drop its animation, and an SVG isn't a raster image for
     * Intervention to resize in the first place.
     */
    private const PASSTHROUGH_MIME_TYPES = ['image/gif', 'image/svg+xml'];

    /**
     * Optimize an uploaded image and store it, returning the same shape of
     * data ('path', 'mime_type', 'size') callers already build by hand from
     * the raw UploadedFile — so this is a drop-in replacement for that.
     *
     * @return array{path: string, mime_type: string, size: int}
     */
    public static function store(UploadedFile $file, string $directory, string $disk = 'public', int $maxDimension = self::MAX_DIMENSION): array
    {
        $mime = $file->getMimeType();

        if (in_array($mime, self::PASSTHROUGH_MIME_TYPES, true)) {
            $path = $file->store($directory, $disk);

            return [
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
            ];
        }

        $manager = new ImageManager(new Driver);
        $image = $manager->decodePath($file->getRealPath());
        $image->scaleDown($maxDimension, $maxDimension);

        $encoded = $image->encode(new WebpEncoder(quality: self::QUALITY));

        $path = rtrim($directory, '/').'/'.Str::random(40).'.webp';

        Storage::disk($disk)->put($path, $encoded->toString());

        return [
            'path' => $path,
            'mime_type' => 'image/webp',
            'size' => $encoded->size(),
        ];
    }
}
