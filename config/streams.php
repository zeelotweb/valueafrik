<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Collaboration eligibility
    |--------------------------------------------------------------------------
    |
    | Live streams are one host broadcasting to many viewers, but a viewer
    | can ask to join as a second publisher ("collaborate") — a lightweight
    | co-host, not a moderator role. Gated the same way early-access
    | features are gated everywhere else on the platform: either you've
    | shown real engagement (Bridge Score), or you're a paying subscriber.
    | This threshold reuses the existing Bridge Score badge tiers (see
    | config/bridge_score.php) rather than inventing a separate number —
    | "Bridge Builder" is the second of four tiers, a deliberate middle
    | ground between "anyone" and "only the most active users."
    |
    */

    'collaboration_bridge_score_threshold' => env('STREAM_COLLABORATION_BRIDGE_SCORE_THRESHOLD', 50),

];
