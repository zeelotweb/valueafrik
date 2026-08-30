<?php

namespace Database\Seeders;

use App\Models\Heritage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HeritageSeeder extends Seeder
{
    /**
     * Broad, national/regional-level starting set spanning every world region.
     * Users can add more specific sub-identities (e.g. "Yoruba", "Bavarian")
     * on top of this list — new entries become available to everyone via a
     * unique slug, so the list grows without duplicating existing ones.
     *
     * @var list<string>
     */
    private const HERITAGES = [
        // Africa
        'Nigerian', 'Ghanaian', 'Kenyan', 'Ethiopian', 'Egyptian', 'South African',
        'Senegalese', 'Ivorian', 'Congolese', 'Tanzanian', 'Ugandan', 'Moroccan',
        'Algerian', 'Zimbabwean', 'Cameroonian', 'Malian', 'Somali', 'Rwandan',

        // Asia
        'Chinese', 'Japanese', 'Korean', 'Indian', 'Pakistani', 'Bangladeshi',
        'Filipino', 'Vietnamese', 'Indonesian', 'Thai', 'Malaysian', 'Sri Lankan',
        'Nepali', 'Mongolian', 'Taiwanese', 'Cambodian', 'Laotian', 'Burmese',

        // Middle East
        'Arab', 'Persian (Iranian)', 'Turkish', 'Kurdish', 'Lebanese', 'Syrian',
        'Palestinian', 'Israeli', 'Iraqi', 'Saudi', 'Emirati', 'Yemeni',

        // Europe
        'Irish', 'Scottish', 'Welsh', 'English', 'French', 'German', 'Italian',
        'Spanish', 'Portuguese', 'Polish', 'Greek', 'Dutch', 'Swedish',
        'Norwegian', 'Danish', 'Finnish', 'Ukrainian', 'Russian', 'Romanian',
        'Hungarian', 'Czech', 'Serbian', 'Croatian', 'Armenian', 'Georgian',

        // Americas
        'Mexican', 'Brazilian', 'Colombian', 'Argentine', 'Peruvian', 'Chilean',
        'Cuban', 'Jamaican', 'Haitian', 'Dominican', 'Puerto Rican', 'Trinidadian',
        'Native American', 'First Nations (Canadian)', 'Quebecois',

        // Oceania
        'Aboriginal Australian', 'Māori', 'Samoan', 'Fijian', 'Tongan', 'Papua New Guinean',
    ];

    public function run(): void
    {
        foreach (self::HERITAGES as $name) {
            Heritage::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
