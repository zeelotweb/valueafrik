<?php

namespace App\Console\Commands;

use App\Support\TranslationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslationsAudit extends Command
{
    protected $signature = 'translations:audit
        {locale? : Show the strings still missing for this locale}
        {--skeleton : With a locale, print a JSON skeleton of the missing strings (English as the value) for a translator}
        {--write : With a locale, add the missing strings to lang/app/<locale>.json with the English text as a placeholder}';

    protected $description = 'Find every translatable string in the code and report what each language is missing';

    public function handle(): int
    {
        $keys = TranslationCatalog::keys();
        $locales = array_keys(config('locales.available'));

        if ($locale = $this->argument('locale')) {
            if (! in_array($locale, $locales, true)) {
                $this->error("Unknown locale '{$locale}'. Available: ".implode(', ', $locales));

                return self::FAILURE;
            }

            $missing = array_values(array_filter($keys, fn ($k) => ! $this->translated($k, $locale)));

            if ($this->option('skeleton')) {
                $this->line(json_encode(array_combine($missing, $missing), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            if ($this->option('write')) {
                $path = lang_path("app/{$locale}.json");
                $existing = File::exists($path) ? json_decode(File::get($path), true) : [];
                foreach ($missing as $k) {
                    $existing[$k] = $k;
                }
                ksort($existing);
                File::ensureDirectoryExists(dirname($path));
                File::put($path, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                $this->info(count($missing)." placeholder strings written to lang/app/{$locale}.json");

                return self::SUCCESS;
            }

            $this->info(count($missing).' of '.count($keys)." strings missing for {$locale}");
            foreach ($missing as $k) {
                $this->line('  '.$k);
            }

            return self::SUCCESS;
        }

        $this->info(count($keys).' translatable strings found in the code');
        $this->table(['Locale', 'Translated', 'Missing', 'Coverage'], collect($locales)->reject(fn ($l) => $l === config('locales.default'))->map(function ($l) use ($keys) {
            $done = count(array_filter($keys, fn ($k) => $this->translated($k, $l)));

            return [$l, $done, count($keys) - $done, count($keys) ? round($done / count($keys) * 100).'%' : '—'];
        })->all());

        return self::SUCCESS;
    }

    private function translated(string $key, string $locale): bool
    {
        static $cache = [];

        $cache[$locale] ??= TranslationCatalog::load($locale);

        // A placeholder (value identical to the English key) is not a translation.
        return isset($cache[$locale][$key]) && $cache[$locale][$key] !== $key;
    }
}
