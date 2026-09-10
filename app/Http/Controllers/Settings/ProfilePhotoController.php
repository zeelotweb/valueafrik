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
        return $this->update($request, 'avatar_path', 'avatars', maxKilobytes: 5120, maxDimension: 1024);
    }

    public function updateCover(Request $request): JsonResponse
    {
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

        $optimized = ImageOptimizer::store($request->file('photo'), $directory, 'public', $maxDimension);

        $profile->update([$column => $optimized['path']]);

        return response()->json([
            'url' => Storage::disk('public')->url($optimized['path']),
        ]);
    }
}
