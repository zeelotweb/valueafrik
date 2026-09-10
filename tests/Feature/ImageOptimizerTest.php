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

test('a small image is not upscaled', function () {
    $file = UploadedFile::fake()->image('small.jpg', 200, 150);

    $result = ImageOptimizer::store($file, 'test-media', 'public', maxDimension: 2048);

    $stored = (new ImageManager(new Driver))->decodePath(Storage::disk('public')->path($result['path']));

    expect($stored->width())->toBe(200);
    expect($stored->height())->toBe(150);
});

test('animated gifs pass through untouched instead of being re-encoded', function () {
    $file = UploadedFile::fake()->create('animated.gif', 50, 'image/gif');

    $result = ImageOptimizer::store($file, 'test-media', 'public');

    expect($result['mime_type'])->toBe('image/gif');
    expect($result['path'])->toEndWith('.gif');
    Storage::disk('public')->assertExists($result['path']);
});
