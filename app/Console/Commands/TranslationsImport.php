<?php

namespace App\Console\Commands;

use App\Support\TranslationCatalog;
use Illuminate\Console\Command;

class TranslationsImport extends Command
{
    protected $signature = 'translations:import {locale : e.g. yo} {file : CSV from translations:export, with corrected translations} {--dry-run : Show what would change without writing}';

    protected $description = 'Bring a reviewer\'s corrected CSV back into lang/app/<locale>.json';

    public function handle(): int
    {
        $locale = $this->argument('locale');
        $file = $this->argument('file');

        if (! array_key_exists($locale, config('locales.available')) || ! is_file($file)) {
            $this->error('Unknown locale or file not found.');

            return self::FAILURE;
        }

        $current = TranslationCatalog::appFile($locale);
        $effective = TranslationCatalog::load($locale);
        $handle = fopen($file, 'r');
        $changed = $skipped = 0;
        $row = 0;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            $line[0] = ltrim((string) ($line[0] ?? ''), "\xEF\xBB\xBF");
            [$english, $translation] = [$line[0], trim((string) ($line[1] ?? ''))];

            if ($row === 1 && $english === 'English') {
                continue;
            }

            if ($english === '' || $translation === '' || ($effective[$english] ?? null) === $translation) {
                continue;
            }

            if (TranslationCatalog::placeholders($english) !== TranslationCatalog::placeholders($translation)) {
                $this->warn("Row {$row}: placeholders changed, skipped — \"".mb_substr($english, 0, 60).'"');
                $skipped++;

                continue;
            }

            // Some sentences are printed as HTML; a reviewer must never be able to add markup.
            if ((str_contains($translation, '<') || str_contains($translation, '>')) && ! str_contains($english, '<')) {
                $this->warn("Row {$row}: contains < or >, skipped — \"".mb_substr($english, 0, 60).'"');
                $skipped++;

                continue;
            }

            $current[$english] = $translation;
            $changed++;
        }

        fclose($handle);

        if ($this->option('dry-run')) {
            $this->info("{$changed} would change, {$skipped} skipped (dry run, nothing written).");

            return self::SUCCESS;
        }

        TranslationCatalog::save($locale, $current);
        $this->info("{$changed} updated, {$skipped} skipped. lang/app/{$locale}.json saved.");

        return self::SUCCESS;
    }
}
