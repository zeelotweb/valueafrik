<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Message;
use App\Models\Reaction;
use App\Models\User;

class DataExporter
{
    /**
     * Everything the account itself is responsible for, in one portable
     * structure — the GDPR Article 20 / CCPA "download my data" shape.
     *
     * Joint or shared content (messages, Bridge Posts) is scoped to what
     * this user contributed, not the other participant's content — their
     * side of a conversation is their own personal data, not this user's
     * to export.
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
            'messages_sent' => Message::where('user_id', $user->id)->with('conversation.participants')->orderBy('created_at')->get()
                ->map(fn ($message) => [
                    'to' => $message->conversation->participants
                        ->where('id', '!=', $user->id)
                        ->pluck('name')
                        ->implode(', '),
                    'body' => $message->isDeletedForEveryone() ? null : $message->body,
                    'sent_at' => $message->created_at?->toISOString(),
                ])->all(),
        ];
    }
}
