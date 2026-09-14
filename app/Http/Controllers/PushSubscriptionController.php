<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        try {
            $request->user()->updatePushSubscription(
                endpoint: $data['endpoint'],
                key: $data['keys']['p256dh'],
                token: $data['keys']['auth'],
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // A concurrent request (two tabs registering at once) already
            // stored this endpoint — the desired state is reached either
            // way, so this still reports success rather than 500ing.
        }

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['status' => 'unsubscribed']);
    }
}
