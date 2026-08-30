<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfilePhotoController extends Controller
{
    public function updateAvatar(Request $request): JsonResponse
    {
        return $this->update($request, 'avatar_path', 'avatars', maxKilobytes: 5120);
    }

    public function updateCover(Request $request): JsonResponse
    {
        return $this->update($request, 'cover_path', 'covers', maxKilobytes: 8192);
    }

    private function update(Request $request, string $column, string $directory, int $maxKilobytes): JsonResponse
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

        $path = $request->file('photo')->store($directory, 'public');

        $profile->update([$column => $path]);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
