<?php

use App\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

beforeEach(function () {
    Storage::fake('public');
});

test('optimizing a large image shrinks it, re-encodes it as webp, and stores it', function () {
    $file = UploadedFile::fake()->image('big.jpg', 3000, 2000);

    $result = ImageOptimizer::store($file, 'test-media', 'public', maxDimension: 1024);

    expect($result['mime_type'])->toBe('image/webp');
    expect($result['path'])->toEndWith('.webp');
    Storage::disk('public')->assertExists($result['path']);

    $stored = (new ImageManager(new Driver))->decodePath(Storage::disk('public')->path($result['path']));

    expect($stored->width())->toBeLessThanOrEqual(1024);
    expect($stored->height())->toBeLessThanOrEqual(1024);
    expect($result['size'])->toBe(Storage::disk('public')->size($result['path']));
});

test('a full-size image also gets a smaller companion thumbnail', function () {
    $file = UploadedFile::fake()->image('big.jpg', 3000, 2000);

    $result = ImageOptimizer::store($file, 'test-media', 'public', maxDimension: 2048);

    expect($result['thumbnail_path'])->not->toBeNull();
    expect($result['thumbnail_path'])->not->toBe($result['path']);
    Storage::disk('public')->assertExists($result['thumbnail_path']);

    $thumb = (new ImageManager(new Driver))->decodePath(Storage::disk('public')->path($result['thumbnail_path']));

    expect($thumb->width())->toBeLessThanOrEqual(800);
    expect($thumb->height())->toBeLessThanOrEqual(800);
    expect(Storage::disk('public')->size($result['thumbnail_path']))
        ->toBeLessThan(Storage::disk('public')->size($result['path']));
});

test('no thumbnail is generated when the source is already thumbnail-sized', function () {
    $file = UploadedFile::fake()->image('tiny.jpg', 400, 300);

    $result = ImageOptimizer::store($file, 'test-media', 'public', maxDimension: 2048);

    expect($result['thumbnail_path'])->toBeNull();
});

test('generateThumbnail: false skips the thumbnail entirely, for avatars/covers', function () {
    $file = UploadedFile::fake()->image('avatar.jpg', 3000, 2000);

    $result = ImageOptimizer::store($file, 'test-media', 'public', maxDimension: 400, generateThumbnail: false);

    expect($result['thumbnail_path'])->toBeNull();
});

test('a small image is not upscaled', function () {
    $file = UploadedFile::fake()->image('small.jpg', 200, 150);

    $result = ImageOptimizer::store($file, 'test-media', 'public', maxDimension: 2048);

    $stored = (new ImageManager(new Driver))->decodePath(Storage::disk('public')->path($result['path']));

    expect($stored->width())->toBe(200);
    expect($stored->height())->toBe(150);
});

test('Media::thumbnailUrl falls back to the full image when there is no thumbnail', function () {
    $author = \App\Models\User::factory()->create();
    $post = $author->wallPosts()->create(['body' => 'Hello.']);

    $withThumb = $post->media()->create([
        'user_id' => $author->id, 'disk' => 'public', 'type' => 'image',
        'path' => 'wall-media/full.webp', 'thumbnail_path' => 'wall-media/thumb.webp',
        'mime_type' => 'image/webp', 'size' => 100,
    ]);
    $withoutThumb = $post->media()->create([
        'user_id' => $author->id, 'disk' => 'public', 'type' => 'image',
        'path' => 'wall-media/legacy.webp', 'thumbnail_path' => null,
        'mime_type' => 'image/webp', 'size' => 100,
    ]);

    expect($withThumb->thumbnailUrl())->toContain('thumb.webp');
    expect($withoutThumb->thumbnailUrl())->toBe($withoutThumb->url());
});

test('optimizing a large photo succeeds even when the process starts at the platform default 128M memory_limit', function () {
    // Regression guard: generating both derivatives used to clone the
    // full-resolution source twice, which reliably exhausted PHP's
    // default 128M CLI/worker limit on an ordinary 3000x2000 phone
    // photo — a real upload would have fatally crashed in production.
    $original = ini_get('memory_limit');
    ini_set('memory_limit', '128M');

    try {
        $file = UploadedFile::fake()->image('phone-photo.jpg', 3000, 2000);

        $result = ImageOptimizer::store($file, 'test-media', 'public');

        expect($result['thumbnail_path'])->not->toBeNull();
        Storage::disk('public')->assertExists($result['path']);
        Storage::disk('public')->assertExists($result['thumbnail_path']);
    } finally {
        ini_set('memory_limit', $original);
    }
});

test('animated gifs pass through untouched instead of being re-encoded', function () {
    $file = UploadedFile::fake()->create('animated.gif', 50, 'image/gif');

    $result = ImageOptimizer::store($file, 'test-media', 'public');

    expect($result['mime_type'])->toBe('image/gif');
    expect($result['path'])->toEndWith('.gif');
    Storage::disk('public')->assertExists($result['path']);
});
