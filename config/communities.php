<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Community membership cap
    |--------------------------------------------------------------------------
    |
    | Hard ceiling on how many active members a single community can hold.
    |
    */

    'membership_cap' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Community creation milestones
    |--------------------------------------------------------------------------
    |
    | Follower count => total number of communities a user may own at once.
    | Every user gets a base allowance of 1 regardless of followers. The
    | ceiling is 7, reached at 100,000 followers, and stays flat forever
    | after that.
    |
    */

    'creation_milestones' => [
        0 => 1,
        5_000 => 2,
        10_000 => 3,
        20_000 => 4,
        35_000 => 5,
        50_000 => 6,
        100_000 => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitor milestones
    |--------------------------------------------------------------------------
    |
    | Member count => total number of monitor slots a community may fill.
    |
    */

    'monitor_milestones' => [
        500 => 1,
        1_000 => 2,
        2_000 => 3,
        3_500 => 4,
        5_000 => 5,
    ],

];
