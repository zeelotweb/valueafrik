<?php

namespace App\Console\Commands;

use App\Support\BladeText;
use Illuminate\Console\Command;

class TranslationsLint extends Command
{
    protected $signature = 'translations:lint';

    protected $description = 'Fail if untranslated English text has crept into a finished area (config/locales.php: translated_paths)';

    public function handle(): int
    {
        $offenders = self::offenders();

        foreach ($offenders as $file => $strings) {
            $this->error($file);
            foreach ($strings as $s) {
                $this->line('    '.mb_substr($s, 0, 110));
            }
        }

        $offenders === []
            ? $this->info('No untranslated text in '.count(config('locales.translated_paths')).' finished areas.')
            : $this->error(count($offenders).' file(s) contain text that is not wrapped in __().');

        return $offenders === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, list<string>> file => unwrapped strings */
    public static function offenders(): array
    {
        $out = [];

        foreach (TranslationsWrap::files(config('locales.translated_paths', [])) as $file) {
            $source = file_get_contents($file);
            $found = array_merge(BladeText::scan($source), BladeText::suspects($source));

            if ($found !== []) {
                $out[str_replace(base_path().'/', '', $file)] = $found;
            }
        }

        return $out;
    }
}
