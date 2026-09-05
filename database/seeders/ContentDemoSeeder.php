<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\User;
use App\Models\WallPost;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class ContentDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Adds additively to the users/communities BridgeScoreDemoSeeder already
     * created — more wall posts (some with photos), and posts in both
     * communities (Diaspora Inner Circle had none) — so the styled Wall and
     * Community pages can be reviewed with real content instead of empty
     * states. Safe to run once against an already-seeded database; does not
     * create new users or communities.
     */
    public function run(): void
    {
        $liam = User::where('name', 'Liam O\'Brien')->first();
        $fatima = User::where('name', 'Fatima Al-Rashid')->first();
        $diego = User::where('name', 'Diego Torres')->first();
        $yuki = User::where('name', 'Yuki Tanaka')->first();
        $amara = User::where('name', 'Amara Osei')->first();

        if (! $liam || ! $fatima || ! $diego || ! $yuki || ! $amara) {
            $this->command?->warn('Expected demo users not found — run BridgeScoreDemoSeeder first.');

            return;
        }

        $this->wallPost($liam, 'New here and still finding my footing — open to any tips on where to start.');
        $this->wallPost($liam, 'Learning that "home" can mean a few different places at once.', photo: true);

        $this->wallPost($fatima, 'Ramadan prep starting early this year — anyone else already stocking dates?', photo: true);
        $this->wallPost($fatima, 'Cairo traffic at sunset never gets old to me, even after all these years.');

        $this->wallPost($diego, 'Salsa verde or salsa roja — pick a side and defend it.', photo: true);

        $this->wallPost($yuki, 'Sakura season photos from this weekend — Kyoto was worth the crowds.', photo: true);

        $this->wallPost($amara, 'Kente cloth has a pattern for basically every occasion — ask me which one fits yours.', photo: true);
        $this->wallPost($amara, 'Jollof rice debate: Ghana or Nigeria? I have a very biased opinion.');

        $globalKitchen = Community::where('slug', 'global-fusion-kitchen')->first();
        $innerCircle = Community::where('slug', 'diaspora-inner-circle')->first();

        if ($globalKitchen) {
            $this->communityPost($globalKitchen, $amara, 'Weekend project: a fusion recipe that mixes jollof spices into a risotto. Photos incoming.', photo: true);
            $this->communityPost($globalKitchen, $fatima, 'Does anyone have a good koshari recipe they swear by?');
        }

        if ($innerCircle) {
            $this->communityPost($innerCircle, $fatima, 'Glad to have this smaller space — feels easier to be candid here.');
            $this->communityPost($innerCircle, $diego, 'Appreciate the invite. Looking forward to the closer conversations.', photo: true);
        }
    }

    private function wallPost(User $user, string $body, bool $photo = false): void
    {
        $post = $user->wallPosts()->create(['body' => $body]);

        $user->awardBridgeScore('wall_post', $post);

        if ($photo) {
            $this->attachPhoto($post, $user, 'wall-media');
        }
    }

    private function communityPost(Community $community, User $user, string $body, bool $photo = false): void
    {
        $post = $community->posts()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);

        $user->awardBridgeScore('community_post', $post);

        if ($photo) {
            $this->attachPhoto($post, $user, 'community-media');
        }
    }

    private function attachPhoto(WallPost|\App\Models\CommunityPost $post, User $user, string $directory): void
    {
        $file = UploadedFile::fake()->image($directory.'-'.$post->id.'.jpg', 900, 700);
        $path = $file->store($directory, 'public');

        $post->media()->create([
            'user_id' => $user->id,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'type' => 'image',
            'size' => $file->getSize(),
        ]);
    }
}
