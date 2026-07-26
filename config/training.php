<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load guardrails
    |--------------------------------------------------------------------------
    |
    | Thresholds for the injury-risk guardrails on the dashboard. ACWR is the
    | acute:chronic workload ratio; ramp is the week-over-week change in acute
    | (7-day) load as a percentage. Kept here so the bands can be tuned without
    | touching the guardrail logic.
    |
    */

    'acwr' => [
        'safe_min' => 0.8,
        'safe_max' => 1.3,
        'danger' => 1.5,
    ],

    'ramp' => [
        'caution' => 10.0,
        'danger' => 25.0,
    ],

    /*
    | TRIMP above which a session planned as recovery or easy is flagged as
    | having carried real training load.
    */
    'recovery_ceiling' => 50.0,

];
