<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ring duration
    |--------------------------------------------------------------------------
    |
    | How long an outgoing call rings before it's automatically registered
    | as a missed call if the other person hasn't answered.
    |
    */

    'ring_seconds' => env('CALL_RING_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Online threshold
    |--------------------------------------------------------------------------
    |
    | A user is considered "online" if we've seen a request from them within
    | this many seconds. Presence is heartbeat-based (see TrackLastSeen),
    | not a live socket roster, so this window absorbs normal gaps between
    | requests.
    |
    */

    'online_threshold_seconds' => env('CALL_ONLINE_THRESHOLD_SECONDS', 45),

];
