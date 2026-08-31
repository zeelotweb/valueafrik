<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Point values
    |--------------------------------------------------------------------------
    |
    | The guiding rule: engagement outweighs output. Posting content earns a
    | little; genuinely connecting with someone — especially across a
    | heritage line — earns a lot more.
    |
    */

    'points' => [
        'roots_completed' => 15,
        'follow' => 1,
        'follow_cross_heritage_bonus' => 3,
        'followed_by_someone' => 1,
        'wall_post' => 1,
        'community_joined' => 2,
        'community_post' => 1,
        'conversation_started' => 3,
        'promoted_to_monitor' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Badge thresholds
    |--------------------------------------------------------------------------
    |
    | Score => badge. A user's badge is always the highest threshold they've
    | crossed; there's no separate "earned badges" table since it's purely
    | derived from the running score.
    |
    */

    'badges' => [
        10 => ['key' => 'first_bridge', 'name' => 'First Bridge'],
        50 => ['key' => 'bridge_builder', 'name' => 'Bridge Builder'],
        150 => ['key' => 'culture_connector', 'name' => 'Culture Connector'],
        500 => ['key' => 'bridge_architect', 'name' => 'Bridge Architect'],
    ],

];
