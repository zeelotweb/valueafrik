<?php

/*
 | Languages the interface can be shown in. To add one:
 |   1. copy Laravel's own messages (validation etc.) into lang/<code>/ and lang/<code>.json,
 |   2. add `lang/app/<code>.json` with the app's strings (`php artisan translations:audit <code> --write`),
 |   3. add it here.
 |
 | 'beta' marks translations that haven't been reviewed by a native speaker;
 | the language menu labels them so nobody mistakes a rough draft for final.
 | 'dir' is the writing direction (rtl languages need a layout pass first).
 */
return [
    'default' => 'en',

    /*
     | Screens whose visible text is fully wrapped in __(). `translations:lint`
     | (and the test suite) fail if plain English text appears in any of these,
     | so a copy change can't quietly leave a language behind. Add an area here
     | when its translation is finished.
     */
    'translated_paths' => [
        'resources/views/welcome.blade.php',
        'resources/views/guide.blade.php',
        'resources/views/roadmap.blade.php',
        'resources/views/partials',
        'resources/views/layouts',
        'resources/views/components/language-switcher.blade.php',
        'resources/views/pages',
    ],

    'available' => [
        'en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr', 'beta' => false],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'dir' => 'ltr', 'beta' => true],
        'fr' => ['name' => 'French', 'native' => 'Français', 'dir' => 'ltr', 'beta' => true],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'dir' => 'ltr', 'beta' => true],
        'pt_BR' => ['name' => 'Portuguese (Brazil)', 'native' => 'Português', 'dir' => 'ltr', 'beta' => true],
        'zh_CN' => ['name' => 'Chinese (Simplified)', 'native' => '简体中文', 'dir' => 'ltr', 'beta' => true],
        'hi' => ['name' => 'Hindi', 'native' => 'हिन्दी', 'dir' => 'ltr', 'beta' => true],
        'sw' => ['name' => 'Swahili', 'native' => 'Kiswahili', 'dir' => 'ltr', 'beta' => true],
        'yo' => ['name' => 'Yoruba', 'native' => 'Yorùbá', 'dir' => 'ltr', 'beta' => true],
        'ha' => ['name' => 'Hausa', 'native' => 'Hausa', 'dir' => 'ltr', 'beta' => true],
        'ig' => ['name' => 'Igbo', 'native' => 'Igbo', 'dir' => 'ltr', 'beta' => true],
    ],
];
