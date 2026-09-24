<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\CommunityPost;
use App\Models\User;
use App\Models\WallPost;
use Illuminate\Database\Eloquent\Model;

class Mentioned extends AppNotification
{
    public function __construct(public Model $mentionable, public User $mentioner) {}

    public function toArray(object $notifiable): array
    {
        // Two whole sentences, not one with a translated "a comment"/"a post"
        // spliced in — word order differs between languages.
        $message = $this->mentionable instanceof Comment
            ? __(':name mentioned you in a comment.', ['name' => $this->mentioner->name])
            : __(':name mentioned you in a post.', ['name' => $this->mentioner->name]);

        return [
            'message' => $message,
            'url' => $this->url(),
        ];
    }

    /**
     * Comments don't have a URL of their own — they're shown inline on the
     * post they belong to, so a mention in a comment resolves to that
     * post's page rather than deep-linking into the comments modal.
     */
    private function url(): string
    {
        $target = $this->mentionable instanceof Comment
            ? $this->mentionable->commentable
            : $this->mentionable;

        return match (true) {
            $target instanceof WallPost => route('profile.show', $target->user),
            $target instanceof CommunityPost => route('communities.show', $target->community),
            default => route('dashboard'),
        };
    }
}
