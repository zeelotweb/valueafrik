<?php

/*
| Features that are built but switched off until launch. A disabled feature
| keeps its code and routes, but its links show a "Coming soon" badge and its
| pages and actions are unavailable.
*/
return [
    // Live streams, 1:1 calls and Culture Sprint.
    'live' => (bool) env('FEATURE_LIVE', false),
];
