<?php

namespace App\Support;

use App\Models\Hashtag;
use App\Models\User;
use App\Notifications\Mentioned as MentionedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Extraction, persistence, and rendering for #hashtags and @mentions in
 * post/comment bodies — one place for all three so the regex and the
 * rendering it feeds stay in sync.
 */
class RichText
{
    /**
     * @return list<string> lowercase, deduped hashtag names (no leading #)
     */
    public static function extractHashtags(string $body): array
    {
        preg_match_all('/(?<![\w#])#([a-zA-Z0-9_]{2,50})/u', $body, $matches);

        return collect($matches[1])
            ->map(fn ($tag) => mb_strtolower($tag))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string> lowercase, deduped usernames (no leading @)
     */
    public static function extractMentionUsernames(string $body): array
    {
        preg_match_all('/(?<![\w@])@([a-zA-Z0-9_-]{2,50})/u', $body, $matches);

        return collect($matches[1])
            ->map(fn ($username) => mb_strtolower($username))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sync a taggable model's hashtags to whatever #tokens are currently in
     * its body — called after every create/update, so editing a post's
     * hashtags away actually removes them.
     */
    public static function syncHashtags(Model $taggable, string $body): void
    {
        $names = self::extractHashtags($body);

        $ids = collect($names)->map(
            fn ($name) => Hashtag::firstOrCreate(['name' => $name], ['slug' => Str::slug($name)])->id
        );

        $taggable->hashtags()->sync($ids);
    }

    /**
     * Sync a mentionable model's mentions to whatever @tokens are currently
     * in its body, and notify anyone newly mentioned — re-saving with the
     * same mention doesn't re-notify; removing one deletes the record.
     */
    public static function syncMentions(Model $mentionable, string $body, User $actor): void
    {
        $usernames = self::extractMentionUsernames($body);

        $users = $usernames === [] ? collect() : User::whereIn('username', $usernames)->get();

        $existingUserIds = $mentionable->mentions()->pluck('user_id')->all();
        $newUserIds = $users->pluck('id')->all();

        $mentionable->mentions()->whereNotIn('user_id', $newUserIds)->delete();

        foreach ($users as $user) {
            if (in_array($user->id, $existingUserIds, true)) {
                continue;
            }

            $mentionable->mentions()->create(['user_id' => $user->id]);

            if ($user->id !== $actor->id) {
                SafeNotifier::send($user, new MentionedNotification($mentionable, $actor));
            }
        }
    }

    /**
     * Render body text with #hashtag and @mention tokens turned into links.
     * Only tokens actually recorded for this content (via the eager-loaded
     * $hashtags/$mentions collections) are linkified — never a coincidental
     * "#word" or "@word" the content doesn't actually carry a record for.
     */
    public static function render(string $body, SupportCollection $hashtags, SupportCollection $mentions): HtmlString
    {
        $html = e($body);

        foreach ($mentions as $mention) {
            $user = $mention->user;

            if (! $user) {
                continue;
            }

            $html = self::linkify($html, '@'.$user->username, route('profile.show', $user));
        }

        foreach ($hashtags as $hashtag) {
            $html = self::linkify($html, '#'.$hashtag->name, route('topics.show', $hashtag));
        }

        return new HtmlString($html);
    }

    private static function linkify(string $html, string $token, string $url): string
    {
        // Case-insensitive match against the stored (lowercase) token, but
        // the replacement keeps whatever case the author actually typed —
        // $matches[0] is the real substring, not the lowercase needle.
        $pattern = '/(?<!\w)'.preg_quote($token, '/').'(?!\w|-)/ui';

        $replacement = fn (array $matches) => '<a href="'.e($url).'" class="font-medium text-cyan-600 hover:underline dark:text-cyan-400">'.$matches[0].'</a>';

        return preg_replace_callback($pattern, $replacement, $html) ?? $html;
    }
}
