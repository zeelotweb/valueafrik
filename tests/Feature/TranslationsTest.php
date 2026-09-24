<?php

use App\Console\Commands\TranslationsLint;
use App\Support\TranslationCatalog;

test('no plain English text has crept into a finished area', function () {
    expect(TranslationsLint::offenders())->toBe([]);
});

test('every translation keeps exactly the placeholders of its English sentence', function () {
    $broken = [];

    foreach (array_keys(config('locales.available')) as $locale) {
        foreach (TranslationCatalog::appFile($locale) as $english => $translated) {
            if (TranslationCatalog::placeholders($english) !== TranslationCatalog::placeholders($translated)) {
                $broken[] = "{$locale}: {$english}";
            }
        }
    }

    expect($broken)->toBe([]);
});

test('no translation can smuggle markup into a sentence printed as HTML', function () {
    $offenders = [];

    foreach (array_keys(config('locales.available')) as $locale) {
        foreach (TranslationCatalog::appFile($locale) as $english => $translated) {
            if ((str_contains($translated, '<') || str_contains($translated, '>')) && ! str_contains($english, '<')) {
                $offenders[] = "{$locale}: {$english}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('every configured language has a file for the app\'s own strings', function () {
    foreach (array_keys(config('locales.available')) as $locale) {
        if ($locale === config('locales.default')) {
            continue;
        }

        expect(lang_path("app/{$locale}.json"))->toBeFile();
    }
});

test('every language translates nearly all of the interface, not just a few screens', function () {
    $keys = TranslationCatalog::keys();
    $thin = [];

    foreach (array_keys(config('locales.available')) as $locale) {
        if ($locale === config('locales.default')) {
            continue;
        }

        $translations = TranslationCatalog::load($locale);
        $translated = count(array_filter($keys, fn ($key) => isset($translations[$key]) && $translations[$key] !== $key));

        // Brand names, "Beta" and words spelled the same in a language legitimately match the English.
        if ($translated / count($keys) < 0.95) {
            $thin[] = $locale;
        }
    }

    expect($thin)->toBe([]);
});

test('sentences that wrap names in links are translated whole', function () {
    $this->app->setLocale('es');

    expect(__(':name is calling you', ['name' => 'Ada']))->toBe('Ada te está llamando')
        ->and(__('hosted by :name', ['name' => 'Ada']))->not->toBe('hosted by Ada');
});
