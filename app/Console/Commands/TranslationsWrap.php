<?php

namespace App\Console\Commands;

use App\Support\BladeText;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class TranslationsWrap extends Command
{
    protected $signature = 'translations:wrap
        {paths* : Blade files or folders (relative to the project root)}
        {--write : Actually rewrite the files (default is a dry run that only counts)}
        {--show : List every string found}';

    protected $description = 'Wrap plain English text in Blade views with __() so it can be translated (dry run unless --write)';

    public function handle(): int
    {
        $total = 0;

        foreach (self::files($this->argument('paths')) as $file) {
            [$out, $found] = BladeText::process(file_get_contents($file));

            if ($found === []) {
                continue;
            }

            $total += count($found);
            $this->line(sprintf('%4d  %s', count($found), str_replace(base_path().'/', '', $file)));

            if ($this->option('show')) {
                foreach ($found as $s) {
                    $this->line('        '.mb_substr($s, 0, 110));
                }
            }

            if ($this->option('write')) {
                file_put_contents($file, $out);
            }
        }

        $this->info(($this->option('write') ? 'Wrapped ' : 'Would wrap ').$total.' strings.');

        return self::SUCCESS;
    }

    /** @return list<string> absolute paths of the Blade files under the given paths */
    public static function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            $full = str_starts_with($path, '/') ? $path : base_path($path);

            if (is_file($full)) {
                $files[] = $full;
            } elseif (is_dir($full)) {
                foreach ((new Finder)->files()->in($full)->name('*.blade.php') as $f) {
                    $files[] = $f->getRealPath();
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }
}
