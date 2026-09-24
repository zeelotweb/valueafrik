<?php

namespace App\Support;

use Illuminate\Translation\FileLoader;
use RuntimeException;

/**
 * Same as Laravel's loader, except JSON files from added paths (lang/app)
 * are applied last, so the app's own wording wins over the framework
 * package's (laravel-lang) wherever both translate the same string.
 */
class AppFileLoader extends FileLoader
{
    protected function loadJsonPaths($locale)
    {
        $output = [];

        foreach (array_merge($this->paths, $this->jsonPaths) as $path) {
            if ($this->files->exists($full = "{$path}/{$locale}.json")) {
                $decoded = json_decode($this->files->get($full), true);

                if (is_null($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException("Translation file [{$full}] contains an invalid JSON structure.");
                }

                $output = array_merge($output, $decoded);
            }
        }

        return $output;
    }
}
