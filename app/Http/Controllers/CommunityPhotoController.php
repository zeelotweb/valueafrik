<?php

namespace App\Http\Controllers;

use App\Models\Community;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CommunityPhotoController extends Controller
{
    public function updateAvatar(Request $request, Community $community): JsonResponse
    {
        return $this->update($request, $community, 'avatar_path', 'community-avatars', maxKilobytes: 5120);
    }

    public function updateCover(Request $request, Community $community): JsonResponse
    {
        return $this->update($request, $community, 'cover_path', 'community-covers', maxKilobytes: 8192);
    }

    private function update(Request $request, Community $community, string $column, string $directory, int $maxKilobytes): JsonResponse
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

        $path = $request->file('photo')->store($directory, 'public');

        $community->update([$column => $path]);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
