<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;

/**
 * Every photo upload in the app (posts, messages, avatars, covers) funnels
 * through here so it lands on disk already shrunk and re-encoded, instead
 * of storing whatever multi-megabyte file a phone camera produced.
 *
 * Post/message media additionally gets a small "thumbnail" derivative —
 * every feed grid, chat bubble, and dashboard card was serving the full
 * (up to 2048px) image just to shrink it in CSS, which is real bandwidth
 * wasted on every single feed load. The full image is now fetched only
 * when the lightbox actually opens it (see Media::thumbnailUrl()).
 */
class ImageOptimizer
{
    private const MAX_DIMENSION = 2048;

    private const THUMBNAIL_DIMENSION = 800;

    private const QUALITY = 82;

    private const THUMBNAIL_QUALITY = 75;

    /**
     * The floor this raises PHP's memory_limit to before touching GD —
     * decoding a modern phone photo (4000x3000+) plus the working buffers
     * GD needs during a resize can spike well past the platform's default
     * 128M. Octane reuses the same worker process across requests, so this
     * only ever raises the ceiling for whichever workers happen to handle
     * an image upload — it never lowers it, and it isn't undone afterward
     * because a higher ceiling costs nothing for requests that don't need it.
     */
    private const MIN_MEMORY_LIMIT_BYTES = 256 * 1024 * 1024;

    /**
     * Animated GIFs and SVGs pass through untouched — re-encoding a GIF to
     * WebP would drop its animation, and an SVG isn't a raster image for
     * Intervention to resize in the first place. Neither gets a thumbnail.
     */
    private const PASSTHROUGH_MIME_TYPES = ['image/gif', 'image/svg+xml'];

    /**
     * Optimize an uploaded image and store it, returning the same shape of
     * data ('path', 'thumbnail_path', 'mime_type', 'size') callers already
     * build by hand from the raw UploadedFile — so this is a drop-in
     * replacement for that.
     *
     * $generateThumbnail is off for avatars/covers: those are single-path
     * columns on Profile/Community, not Media rows, so a thumbnail_path
     * would just be generated and never referenced by anything.
     *
     * @return array{path: string, thumbnail_path: ?string, mime_type: string, size: int}
     */
    public static function store(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $maxDimension = self::MAX_DIMENSION,
        bool $generateThumbnail = true,
    ): array {
        $mime = $file->getMimeType();

        if (in_array($mime, self::PASSTHROUGH_MIME_TYPES, true)) {
            $path = $file->store($directory, $disk);

            return [
                'path' => $path,
                'thumbnail_path' => null,
                'mime_type' => $mime,
                'size' => $file->getSize(),
            ];
        }

        self::ensureAdequateMemoryLimit();

        $manager = new ImageManager(new Driver);
        $image = $manager->decodePath($file->getRealPath());

        $sourceWidth = $image->width();
        $sourceHeight = $image->height();

        // Mutates $image in place — deliberately not cloned. Two full-
        // resolution copies alive at once (one being resized) is exactly
        // what was blowing through the memory limit on a large photo.
        $image->scaleDown($maxDimension, $maxDimension);
        $full = self::encodeAndStore($image, self::QUALITY, $directory, $disk);

        $thumbnailPath = null;

        // Skip a separate thumbnail when the source was already no bigger
        // than one — it'd just be a redundant near-duplicate file. Derived
        // from the already-shrunk $image (now at most $maxDimension), not
        // the original — cheaper, and visually identical at 800px either way.
        if ($generateThumbnail && ($sourceWidth > self::THUMBNAIL_DIMENSION || $sourceHeight > self::THUMBNAIL_DIMENSION)) {
            $image->scaleDown(self::THUMBNAIL_DIMENSION, self::THUMBNAIL_DIMENSION);
            $thumbnail = self::encodeAndStore($image, self::THUMBNAIL_QUALITY, $directory, $disk);
            $thumbnailPath = $thumbnail['path'];
        }

        return [
            'path' => $full['path'],
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => 'image/webp',
            'size' => $full['size'],
        ];
    }

    /**
     * @return array{path: string, size: int}
     */
    private static function encodeAndStore(Image $image, int $quality, string $directory, string $disk): array
    {
        $encoded = $image->encode(new WebpEncoder(quality: $quality));
        $path = rtrim($directory, '/').'/'.Str::random(40).'.webp';

        Storage::disk($disk)->put($path, $encoded->toString());

        return ['path' => $path, 'size' => $encoded->size()];
    }

    private static function ensureAdequateMemoryLimit(): void
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return;
        }

        $unit = strtoupper(substr($limit, -1));
        $bytes = (int) $limit * match ($unit) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        if ($bytes < self::MIN_MEMORY_LIMIT_BYTES) {
            ini_set('memory_limit', self::MIN_MEMORY_LIMIT_BYTES);
        }
    }
}
