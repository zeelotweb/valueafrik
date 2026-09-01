<?php

namespace App\Services;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use App\Models\LiveSession;
use App\Models\User;

class LiveKitToken
{
    /**
     * Generate a join token scoped to this user's permissions in this
     * specific session — routed through config(), not the SDK's own
     * getenv() fallback, since getenv() is unreliable under Octane's
     * long-lived workers.
     */
    public static function generate(LiveSession $session, User $user): string
    {
        $options = (new AccessTokenOptions())
            ->setIdentity((string) $user->id)
            ->setName($user->name);

        $token = new AccessToken(
            config('services.livekit.api_key'),
            config('services.livekit.api_secret'),
            $options,
        );

        $grant = new VideoGrant();
        $grant->setRoomName($session->room_name);
        $grant->setRoomJoin(true);
        $grant->setCanPublish($session->canPublish($user));
        $grant->setCanSubscribe(true);

        $token->setGrant($grant);

        return $token->toJwt();
    }
}
