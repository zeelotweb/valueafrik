<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Services\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CommunityPhotoController extends Controller
{
    public function updateAvatar(Request $request, Community $community): JsonResponse
    {
        // Same reasoning as ProfilePhotoController — the largest community
        // avatar shown anywhere is an 80px circle, so 400px is generous
        // headroom, not a compromise.
        return $this->update($request, $community, 'avatar_path', 'community-avatars', maxKilobytes: 5120, maxDimension: 400);
    }

    public function updateCover(Request $request, Community $community): JsonResponse
    {
        return $this->update($request, $community, 'cover_path', 'community-covers', maxKilobytes: 8192, maxDimension: 2048);
    }

    private function update(Request $request, Community $community, string $column, string $directory, int $maxKilobytes, int $maxDimension): JsonResponse
    {
        abort_unless($request->user()->id === $community->owner_id, 403);

        $request->validate([
            'photo' => [
                'required',
                'image',
                Rule::dimensions()->maxWidth(6000)->maxHeight(6000),
                'max:'.$maxKilobytes,
            ],
        ]);

        if ($community->{$column}) {
            Storage::disk('public')->delete($community->{$column});
        }

        $optimized = ImageOptimizer::store($request->file('photo'), $directory, 'public', $maxDimension, generateThumbnail: false);

        $community->update([$column => $optimized['path']]);

        return response()->json([
            'url' => Storage::disk('public')->url($optimized['path']),
        ]);
    }
}
