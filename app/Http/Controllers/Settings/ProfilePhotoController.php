<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfilePhotoController extends Controller
{
    public function updateAvatar(Request $request): JsonResponse
    {
        // The largest avatar ever rendered anywhere in the app is a 112px
        // circle on the profile page — 400px comfortably covers that at
        // 3x retina with no real quality difference from 1024px, at a
        // fraction of the file size. It never needs its own thumbnail
        // tier the way post media does: there's no "shown huge" context
        // to serve a bigger derivative for.
        return $this->update($request, 'avatar_path', 'avatars', maxKilobytes: 5120, maxDimension: 400);
    }

    public function updateCover(Request $request): JsonResponse
    {
        // Covers genuinely render near full card width (up to ~768px),
        // so they keep the same ceiling post media uses.
        return $this->update($request, 'cover_path', 'covers', maxKilobytes: 8192, maxDimension: 2048);
    }

    private function update(Request $request, string $column, string $directory, int $maxKilobytes, int $maxDimension): JsonResponse
    {
        $request->validate([
            'photo' => [
                'required',
                'image',
                Rule::dimensions()->maxWidth(6000)->maxHeight(6000),
                'max:'.$maxKilobytes,
            ],
        ]);

        $profile = $request->user()->profile()->firstOrCreate([]);

        if ($profile->{$column}) {
            Storage::disk('public')->delete($profile->{$column});
        }

        $optimized = ImageOptimizer::store($request->file('photo'), $directory, 'public', $maxDimension, generateThumbnail: false);

        $profile->update([$column => $optimized['path']]);

        return response()->json([
            'url' => Storage::disk('public')->url($optimized['path']),
        ]);
    }
}
