<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LanguageSeeder extends Seeder
{
    /**
     * The world's most widely spoken languages, spanning every major region.
     *
     * @var list<string>
     */
    private const LANGUAGES = [
        'English', 'Mandarin Chinese', 'Hindi', 'Spanish', 'French', 'Arabic',
        'Bengali', 'Portuguese', 'Russian', 'Urdu', 'Indonesian', 'German',
        'Japanese', 'Swahili', 'Marathi', 'Telugu', 'Turkish', 'Tamil',
        'Yoruba', 'Vietnamese', 'Korean', 'Italian', 'Hausa', 'Thai',
        'Gujarati', 'Amharic', 'Polish', 'Ukrainian', 'Igbo', 'Malayalam',
        'Punjabi', 'Zulu', 'Xhosa', 'Somali', 'Farsi (Persian)', 'Burmese',
        'Filipino (Tagalog)', 'Dutch', 'Greek', 'Czech', 'Romanian', 'Hungarian',
        'Hebrew', 'Swedish', 'Twi', 'Wolof', 'Afrikaans', 'Nepali',
        'Sinhala', 'Khmer', 'Lao', 'Malay', 'Mandinka', 'Shona',
        'Kinyarwanda', 'Luganda', 'Akan', 'Ewe', 'Fon', 'Creole (Haitian)',
        'Jamaican Patois', 'Quechua', 'Guarani', 'Māori', 'Samoan', 'Fijian',
        'Cantonese', 'Kurdish', 'Pashto', 'Dari', 'Armenian', 'Georgian',
        'Azerbaijani', 'Mongolian', 'Tibetan', 'Sign Language (ASL)',
    ];

    public function run(): void
    {
        foreach (self::LANGUAGES as $name) {
            Language::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
