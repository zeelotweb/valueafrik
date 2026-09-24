<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/** Every translatable string in the code, and what each language has for it. */
class TranslationCatalog
{
    /** @return list<string> */
    public static function keys(): array
    {
        $found = [];
        $files = (new Finder)->files()->in([base_path('app'), base_path('resources/views'), base_path('routes')])->name('*.php');

        foreach ($files as $file) {
            // Staff-only admin screens stay English, so they don't count as missing.
            if ($file->getFilename() === 'BladeText.php' || str_contains($file->getPathname(), '/pages/admin/')) {
                continue;
            }

            if (preg_match_all('/(?:__|trans_choice|trans|@lang)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', $file->getContents(), $m)) {
                foreach ($m[2] as $raw) {
                    $key = stripcslashes($raw);
                    // Dotted keys like "auth.failed" are file-based framework strings, not ours.
                    if ($key !== '' && ! str_starts_with($key, '@') && ! preg_match('/^[a-z_]+(\.[a-z_]+)+$/i', $key)) {
                        $found[$key] = true;
                    }
                }
            }
        }

        // Badge names live in config and are printed through __($badge['name']),
        // which a code scan can't see.
        foreach (config('bridge_score.badges', []) as $badge) {
            $found[$badge['name']] = true;
        }

        $keys = array_keys($found);
        sort($keys);

        return $keys;
    }

    /**
     * The app's own strings for a locale (framework JSON underneath, lang/app on top).
     *
     * @return array<string, string>
     */
    public static function load(string $locale): array
    {
        return array_merge(self::read(lang_path("{$locale}.json")), self::read(lang_path("app/{$locale}.json")));
    }

    /** @return array<string, string> */
    public static function appFile(string $locale): array
    {
        return self::read(lang_path("app/{$locale}.json"));
    }

    /** @param  array<string, string>  $strings */
    public static function save(string $locale, array $strings): void
    {
        ksort($strings);
        File::ensureDirectoryExists(lang_path('app'));
        File::put(lang_path("app/{$locale}.json"), json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    /**
     * Placeholders (:name, {count}) a translation must keep exactly.
     *
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+|\{[a-z_]+\}/', $text, $m);
        sort($m[0]);

        return $m[0];
    }

    /** @return array<string, string> */
    private static function read(string $path): array
    {
        return File::exists($path) ? (json_decode(File::get($path), true) ?: []) : [];
    }
}
