<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InterestSeeder extends Seeder
{
    /**
     * Curiosity topics — what someone wants to explore in other cultures,
     * distinct from Heritage (who they are). Broad and cross-cultural by
     * design, not tied to any one region.
     *
     * @var list<string>
     */
    private const INTERESTS = [
        'Street Food', 'Home Cooking', 'Traditional Medicine', 'Music & Fusion',
        'Dance', 'Festivals & Celebrations', 'Fashion & Textiles', 'Visual Art',
        'Film & Cinema', 'Literature & Poetry', 'Folklore & Mythology',
        'Language Exchange', 'History', 'Spirituality & Faith', 'Weddings & Rites of Passage',
        'Family Traditions', 'Diaspora Life', 'Cross-Cultural Entrepreneurship',
        'Architecture', 'Photography', 'Comedy & Humor', 'Sports & Games',
        'Parenting Across Cultures', 'Fusion Cuisine', 'Anime & Manga',
        'Afrobeat & Amapiano', 'K-Pop & Hallyu', 'Indigenous Practices',
    ];

    public function run(): void
    {
        foreach (self::INTERESTS as $name) {
            Interest::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
