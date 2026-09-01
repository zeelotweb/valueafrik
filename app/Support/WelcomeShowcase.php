<?php

namespace App\Support;

use App\Models\BridgePost;
use App\Models\Community;
use App\Models\Interest;
use App\Models\LiveSession;
use App\Models\User;

/**
 * Real, live demo content for the welcome page — one illustrative example
 * per pillar, pulled from actual records. Every query here is deliberately
 * filtered: no soft-deleted rows, no private/followers-only community
 * content, nothing with a blank body. A pillar with no qualifying record
 * yet is simply left out of the rotation rather than shown empty.
 */
class WelcomeShowcase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(): array
    {
        return collect([
            self::identity(),
            self::bridgePost(),
            self::community(),
            self::bridgeScore(),
            self::discovery(),
            self::live(),
        ])->filter()->values()->all();
    }

    private static function identity(): ?array
    {
        $user = User::query()
            ->whereHas('profile', fn ($query) => $query->whereNotNull('bio')->where('bio', '!=', ''))
            ->whereHas('heritages')
            ->whereHas('languages')
            ->with(['profile', 'heritages', 'languages'])
            ->inRandomOrder()
            ->first();

        if (! $user) {
            return null;
        }

        return [
            'id' => 'identity',
            'number' => '01',
            'title' => 'Identity & Profiles',
            'tagline' => 'Your way of life as your profile.',
            'type' => 'identity',
            'user' => $user,
        ];
    }

    private static function bridgePost(): ?array
    {
        $post = BridgePost::query()
            ->where('status', BridgePost::STATUS_ACTIVE)
            ->whereNotNull('initiator_body')
            ->whereNotNull('partner_body')
            ->with(['initiator.profile', 'partner.profile'])
            ->inRandomOrder()
            ->first();

        if (! $post) {
            return null;
        }

        return [
            'id' => 'content',
            'number' => '02',
            'title' => 'Cultural Spotlights & Bridge Posts',
            'tagline' => 'Co-created posts comparing the same tradition across two cultures.',
            'type' => 'bridge_post',
            'post' => $post,
        ];
    }

    private static function community(): ?array
    {
        $community = Community::query()
            ->where('visibility', Community::VISIBILITY_PUBLIC)
            ->withCount('activeMembers')
            ->with('owner.profile')
            ->inRandomOrder()
            ->get()
            ->first(fn ($c) => $c->active_members_count > 0);

        if (! $community) {
            return null;
        }

        return [
            'id' => 'circles',
            'number' => '03',
            'title' => 'Culture Circles',
            'tagline' => 'Small communities built around curiosity, not virality.',
            'type' => 'community',
            'community' => $community,
        ];
    }

    private static function bridgeScore(): ?array
    {
        $user = User::query()
            ->whereHas('bridgeScoreEvents')
            ->withSum('bridgeScoreEvents', 'points')
            ->with('profile')
            ->orderByDesc('bridge_score_events_sum_points')
            ->first();

        if (! $user || ! $user->bridgeBadge()) {
            return null;
        }

        return [
            'id' => 'bridge-score',
            'number' => '04',
            'title' => 'Bridge Score & Badges',
            'tagline' => 'Recognition for sparking exchange, not just posting.',
            'type' => 'bridge_score',
            'user' => $user,
        ];
    }

    private static function discovery(): ?array
    {
        $pair = Interest::query()
            ->whereHas('users')
            ->with(['users' => fn ($query) => $query->with('profile')])
            ->get()
            ->first(fn ($interest) => $interest->users->count() >= 2);

        if (! $pair) {
            return null;
        }

        [$first, $second] = $pair->users->take(2)->all();

        return [
            'id' => 'discovery',
            'number' => '05',
            'title' => 'Discovery & Matchmaking',
            'tagline' => 'Find people through shared curiosity, not follower overlap.',
            'type' => 'discovery',
            'interest' => $pair,
            'first' => $first,
            'second' => $second,
        ];
    }

    private static function live(): ?array
    {
        $session = LiveSession::query()
            ->whereNotNull('title')
            ->with('host.profile')
            ->inRandomOrder()
            ->first();

        if (! $session) {
            return null;
        }

        return [
            'id' => 'live',
            'number' => '06',
            'title' => 'Live & Video',
            'tagline' => 'Real-time conversation and broadcast.',
            'type' => 'live',
            'session' => $session,
        ];
    }
}
