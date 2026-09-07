<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Accept window
    |--------------------------------------------------------------------------
    |
    | Once two people are matched, how long they each have to confirm
    | they're ready before the match is abandoned and released back to
    | whichever side did respond in time.
    |
    */

    'accept_seconds' => env('CULTURE_SPRINT_ACCEPT_SECONDS', 15),

    /*
    |--------------------------------------------------------------------------
    | Turn length
    |--------------------------------------------------------------------------
    |
    | Each side gets this many seconds to talk, in turn — the sprint ends
    | automatically once both turns are up.
    |
    */

    'turn_seconds' => env('CULTURE_SPRINT_TURN_SECONDS', 20),

    /*
    |--------------------------------------------------------------------------
    | Pool staleness
    |--------------------------------------------------------------------------
    |
    | A pool row older than this was probably left behind by someone who
    | signaled interest and wandered off — it's pruned rather than handed
    | out as a match.
    |
    */

    'pool_stale_minutes' => env('CULTURE_SPRINT_POOL_STALE_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Prompts
    |--------------------------------------------------------------------------
    |
    | One is assigned at random per match — the lens each side shares
    | their culture through.
    |
    */

    'words' => [
        'Weddings',
        'Food',
        'Family',
        'Music',
        'Superstitions',
        'Hospitality',
        'Coming of age',
        'Celebrations',
        'Humor',
        'Mourning & remembrance',
        'Elders',
        'Home',
        'Storytelling',
        'Fashion',
        'Faith',
        'Language',
        'Neighbors',
        'Childhood games',
        'Gift-giving',
        'Proverbs',
    ],

];
