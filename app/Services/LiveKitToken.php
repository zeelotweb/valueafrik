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
     *
     * Streams are meant to be watched by anyone (visibility settings are a
     * separate, not-yet-built concern) — canPublish() already restricts
     * publishing to the host. Calls and sprints are inherently private, so
     * only a participant gets a token at all; this is the last line of
     * defense even if a caller forgets its own participant check.
     */
    public static function generate(LiveSession $session, User $user): string
    {
        abort_unless($session->type === LiveSession::TYPE_STREAM || $session->isParticipant($user), 403);

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
