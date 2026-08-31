<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Heritage;
use App\Models\Language;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BridgeScoreDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds a handful of demo users walked through real Bridge Score actions,
     * so every scenario (cross-heritage follow bonus, Roots completion,
     * community join/post, monitor promotion, a pending private request, and
     * each badge tier) is actually visible in the app, not just proven by tests.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('BridgeScoreDemoSeeder seeds fake demo accounts and refuses to run in production.');

            return;
        }

        $password = Hash::make('password');

        $liam = $this->makeUser('Liam O\'Brien', 'liam@example.com', $password);

        $fatima = $this->makeUser('Fatima Al-Rashid', 'fatima@example.com', $password);
        $this->completeRoots($fatima, 'Connecting cultures, one conversation at a time.', 'EG', ['egyptian'], ['arabic']);

        $diego = $this->makeUser('Diego Torres', 'diego@example.com', $password);
        $this->completeRoots($diego, 'Chef, traveler, always curious about someone else\'s table.', 'MX', ['mexican'], ['spanish']);

        $yuki = $this->makeUser('Yuki Tanaka', 'yuki@example.com', $password);
        $this->completeRoots($yuki, 'Design, tradition, and everything in between.', 'JP', ['japanese'], ['japanese', 'english']);

        $amara = $this->makeUser('Amara Osei', 'amara@example.com', $password);
        $this->completeRoots($amara, 'Building bridges across the diaspora, one bridge at a time.', 'GH', ['ghanaian'], ['twi', 'english']);

        // Follows — several cross-heritage (bonus fires), one same-heritage-set (no bonus),
        // and one toward a user with no declared heritage yet (no bonus, since there's
        // nothing to bridge from on their side).
        $this->follow($fatima, $liam);      // no bonus — liam has no heritage set
        $this->follow($diego, $fatima);     // cross-heritage bonus
        $this->follow($diego, $liam);       // no bonus
        $this->follow($yuki, $diego);       // cross-heritage bonus
        $this->follow($yuki, $fatima);      // cross-heritage bonus
        $this->follow($yuki, $liam);        // no bonus
        $this->follow($amara, $diego);      // cross-heritage bonus
        $this->follow($amara, $fatima);     // cross-heritage bonus
        $this->follow($amara, $yuki);       // cross-heritage bonus

        // Wall posts.
        $fatima->wallPosts()->create(['body' => 'First post here — excited to see who I meet.']);
        $fatima->awardBridgeScore('wall_post');

        foreach ([
            'Cooking mole from scratch today, ask me anything.',
            'What\'s one dish from your culture everyone should try?',
        ] as $body) {
            $diego->wallPosts()->create(['body' => $body]);
            $diego->awardBridgeScore('wall_post');
        }

        foreach ([
            'Sharing a tea ceremony video this week.',
            'How is tradition showing up in your daily life lately?',
            'Grateful for this space — real conversations, not just noise.',
        ] as $body) {
            $yuki->wallPosts()->create(['body' => $body]);
            $yuki->awardBridgeScore('wall_post');
        }

        // Communities — Amara owns a public one everyone joins and posts in.
        $globalKitchen = $amara->ownedCommunities()->create([
            'name' => 'Global Fusion Kitchen',
            'slug' => 'global-fusion-kitchen',
            'description' => 'Trading recipes and food stories across cultures.',
            'visibility' => Community::VISIBILITY_PUBLIC,
            'participation_level' => Community::PARTICIPATION_POST,
        ]);
        $globalKitchen->members()->attach($amara->id, ['role' => 'owner', 'status' => 'active']);

        foreach ([$diego, $yuki, $fatima] as $member) {
            $globalKitchen->members()->attach($member->id, ['role' => 'member', 'status' => 'active']);
            $member->awardBridgeScore('community_joined', $globalKitchen);
        }

        $diego->awardBridgeScore('community_post', $globalKitchen->posts()->create([
            'user_id' => $diego->id,
            'body' => 'Started a thread on street food from home — jump in.',
        ]));

        $yuki->awardBridgeScore('community_post', $globalKitchen->posts()->create([
            'user_id' => $yuki->id,
            'body' => 'Sharing a family recipe passed down three generations.',
        ]));

        // Promote Yuki to monitor — override the milestone locally so a small
        // demo community can still demonstrate the earned-trust scenario.
        config(['communities.monitor_milestones' => [1 => 1]]);
        $globalKitchen->members()->updateExistingPivot($yuki->id, ['role' => 'monitor']);
        $yuki->awardBridgeScore('promoted_to_monitor', $globalKitchen);

        // A private community owned by Fatima, to show both a pending request
        // (Yuki's, left untouched) and an approved one (Diego's).
        $innerCircle = $fatima->ownedCommunities()->create([
            'name' => 'Diaspora Inner Circle',
            'slug' => 'diaspora-inner-circle',
            'description' => 'A small, approval-only space for closer conversations.',
            'visibility' => Community::VISIBILITY_PRIVATE,
            'participation_level' => Community::PARTICIPATION_POST,
        ]);
        $innerCircle->members()->attach($fatima->id, ['role' => 'owner', 'status' => 'active']);

        $innerCircle->members()->attach($diego->id, ['role' => 'member', 'status' => 'active']);
        $diego->awardBridgeScore('community_joined', $innerCircle);

        $innerCircle->members()->attach($yuki->id, ['role' => 'member', 'status' => 'pending']);
        // Left pending on purpose — Fatima will see this in her "Manage community" panel.

        // Conversations.
        $this->startConversation($diego, $fatima);
        $this->startConversation($yuki, $diego);
        $this->startConversation($amara, $yuki);

        // Top up Diego, Yuki, and Amara with additional realistic-reason
        // activity so all four badge tiers are represented, not just two.
        $this->topUpTo($diego, 60);
        $this->topUpTo($yuki, 150);
        $this->topUpTo($amara, 500);
    }

    private function makeUser(string $name, string $email, string $password): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $heritageSlugs
     * @param  array<int, string>  $languageSlugs
     */
    private function completeRoots(User $user, string $bio, string $country, array $heritageSlugs, array $languageSlugs): void
    {
        $user->profile()->updateOrCreate([], [
            'bio' => $bio,
            'country' => $country,
        ]);

        $user->heritages()->sync(Heritage::whereIn('slug', $heritageSlugs)->pluck('id'));
        $user->languages()->sync(Language::whereIn('slug', $languageSlugs)->pluck('id'));

        $user->awardBridgeScore('roots_completed');
    }

    private function follow(User $follower, User $followed): void
    {
        $follower->following()->attach($followed->id);
        $follower->awardBridgeScore('follow', $followed);

        if ($follower->isCrossHeritageWith($followed)) {
            $follower->awardBridgeScore('follow_cross_heritage_bonus', $followed);
        }

        $followed->awardBridgeScore('followed_by_someone', $follower);
    }

    private function startConversation(User $initiator, User $other): void
    {
        $conversation = \App\Models\Conversation::between($initiator, $other);

        $conversation->messages()->create([
            'user_id' => $initiator->id,
            'body' => 'Hey — really enjoyed your last post. Where are you based?',
        ]);

        $initiator->awardBridgeScore('conversation_started', $conversation);
    }

    private function topUpTo(User $user, int $target): void
    {
        while ($user->bridgeScore() < $target) {
            $user->awardBridgeScore('wall_post');
        }
    }
}
