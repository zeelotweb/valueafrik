<?php

namespace App\Console\Commands;

use App\Support\TranslationCatalog;
use Illuminate\Console\Command;

class TranslationsExport extends Command
{
    protected $signature = 'translations:export {locale : e.g. yo} {--path= : Where to write the CSV (default: storage/app/translations-<locale>.csv)}';

    protected $description = 'Write a spreadsheet (CSV) of every string and its translation for a native speaker to review';

    public function handle(): int
    {
        $locale = $this->argument('locale');

        if (! array_key_exists($locale, config('locales.available'))) {
            $this->error("Unknown locale '{$locale}'.");

            return self::FAILURE;
        }

        $path = $this->option('path') ?: storage_path("app/translations-{$locale}.csv");
        $strings = TranslationCatalog::load($locale);

        $out = fopen($path, 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents and scripts correctly
        fputcsv($out, ['English', 'Translation', 'Notes']);

        foreach (TranslationCatalog::keys() as $key) {
            $value = $strings[$key] ?? '';
            fputcsv($out, [$key, $value === $key ? '' : $value, '']);
        }

        fclose($out);
        $this->info("Wrote {$path}");

        return self::SUCCESS;
    }
}
