<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Media;
use App\Models\Reaction;
use App\Models\User;

class DataExporter
{
    /**
     * Everything the account itself is responsible for, in one portable
     * structure — the GDPR Article 20 / CCPA "download my data" shape.
     *
     * Bridge Posts stay scoped to what this user personally wrote — their
     * side of a co-authored post is their own contribution, not the other
     * participant's. Messages are the one deliberate exception: unlike a
     * Bridge Post's shared byline, a message you received is already
     * addressed to you and visible to you in the app regardless of who
     * typed it, so full conversation transcripts (both directions) are
     * included here rather than only what this user sent.
     */
    public static function build(User $user): array
    {
        return [
            'exported_at' => now()->toISOString(),
            'account' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'joined_at' => $user->created_at?->toISOString(),
            ],
            'profile' => [
                'bio' => $user->profile?->bio,
                'country' => $user->profile?->country,
                'languages' => $user->languages()->pluck('name')->all(),
                'heritages' => $user->heritages()->pluck('name')->all(),
                'interests' => $user->interests()->pluck('name')->all(),
                'avatar_url' => $user->profile?->avatarUrl(),
                'cover_url' => $user->profile?->coverUrl(),
            ],
            'bridge_score' => [
                'total' => $user->bridgeScore(),
                'badge' => User::badgeForScore($user->bridgeScore())['name'] ?? null,
                'events' => $user->bridgeScoreEvents()->orderBy('created_at')->get()
                    ->map(fn ($event) => [
                        'reason' => $event->reason,
                        'points' => $event->points,
                        'at' => $event->created_at?->toISOString(),
                    ])->all(),
            ],
            'wall_posts' => $user->wallPosts()->orderBy('created_at')->get()
                ->map(fn ($post) => [
                    'body' => $post->body,
                    'posted_at' => $post->created_at?->toISOString(),
                ])->all(),
            'community_posts' => $user->communityPosts()->with('community')->orderBy('created_at')->get()
                ->map(fn ($post) => [
                    'community' => $post->community?->name,
                    'body' => $post->body,
                    'posted_at' => $post->created_at?->toISOString(),
                ])->all(),
            'comments' => Comment::where('user_id', $user->id)->orderBy('created_at')->get()
                ->map(fn ($comment) => [
                    'body' => $comment->body,
                    'posted_at' => $comment->created_at?->toISOString(),
                ])->all(),
            'reactions_given' => Reaction::where('user_id', $user->id)->orderBy('created_at')->get()
                ->map(fn ($reaction) => [
                    'type' => $reaction->type,
                    'at' => $reaction->created_at?->toISOString(),
                ])->all(),
            'bridge_posts' => $user->bridgePostInvitesSent()->with('partner')->get()
                ->map(fn ($post) => [
                    'theme' => $post->theme,
                    'role' => 'initiator',
                    'other_participant' => $post->partner?->name,
                    'your_side' => $post->initiator_body,
                    'status' => $post->status,
                    'started_at' => $post->created_at?->toISOString(),
                ])
                ->concat($user->bridgePostInvitesReceived()->with('initiator')->get()
                    ->map(fn ($post) => [
                        'theme' => $post->theme,
                        'role' => 'partner',
                        'other_participant' => $post->initiator?->name,
                        'your_side' => $post->partner_body,
                        'status' => $post->status,
                        'started_at' => $post->created_at?->toISOString(),
                    ]))
                ->all(),
            'communities' => $user->communities()->get()
                ->map(fn ($community) => [
                    'name' => $community->name,
                    'role' => $community->pivot->role,
                    'status' => $community->pivot->status,
                    'joined_at' => $community->pivot->created_at?->toISOString(),
                ])->all(),
            'messages' => $user->conversations()
                ->with(['participants', 'messages' => fn ($query) => $query->orderBy('created_at')->with('media')])
                ->get()
                ->map(fn ($conversation) => [
                    'with' => $conversation->participants->where('id', '!=', $user->id)->pluck('name')->implode(', '),
                    'messages' => $conversation->messages->map(fn ($message) => [
                        'from' => $message->user_id === $user->id ? 'You' : $message->user?->name,
                        'body' => $message->isDeletedForEveryone() ? null : $message->body,
                        'media' => $message->isDeletedForEveryone() ? [] : $message->media->map(fn ($media) => $media->url())->all(),
                        'sent_at' => $message->created_at?->toISOString(),
                    ])->all(),
                ])->all(),
            // Only what this user themselves uploaded — a photo someone
            // else attached to a shared post or sent you isn't this user's
            // to export, same principle as the Bridge Post scoping above.
            'media' => Media::where('user_id', $user->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn ($media) => [
                    'context' => match ($media->mediable_type) {
                        \App\Models\Message::class => 'message',
                        \App\Models\WallPost::class => 'wall_post',
                        \App\Models\CommunityPost::class => 'community_post',
                        \App\Models\BridgePost::class => 'bridge_post',
                        default => 'other',
                    },
                    'url' => $media->url(),
                    'uploaded_at' => $media->created_at?->toISOString(),
                ])->all(),
        ];
    }
}
