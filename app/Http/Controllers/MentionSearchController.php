<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json([]);
        }

        $users = User::query()
            ->where(function ($q) use ($query) {
                $q->where('username', 'like', $query.'%')
                    ->orWhere('name', 'like', $query.'%');
            })
            ->with('profile')
            ->limit(6)
            ->get()
            ->map(fn (User $user) => [
                'username' => $user->username,
                'name' => $user->name,
                'avatarUrl' => $user->profile?->avatarUrl(),
            ]);

        return response()->json($users);
    }
}
