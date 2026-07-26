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

    /*
    | Standard distances (metres) for running personal bests and durations
    | (seconds) for the mean-max pace curve.
    */
    'records' => [
        'distances_m' => [1000, 5000, 10000, 21097],
        'durations_s' => [60, 300, 600, 1200, 1800, 3600],
    ],

    /*
    | Share of easy time (HR zones 1-2) that counts as a polarized week.
    */
    'polarization' => [
        'easy_target' => 80.0,
    ],

    /*
    | Race-time predictor. Target distances (metres) and the Riegel fatigue
    | exponent used to extrapolate a reference effort to another distance.
    | fitness_exponent controls how strongly a change in CTL nudges the
    | predicted pace (endurance gains are sublinear, so it is small).
    */
    'predictor' => [
        'distances_m' => [5000, 10000, 21097, 42195],
        'riegel_exponent' => 1.06,
        'fitness_exponent' => 0.10,
    ],

    /*
    | Readiness score at or below which a hard planned session is offered a
    | downgrade.
    */
    'readiness' => [
        'downgrade_below' => 60,
    ],

    /*
    | Forecast horizon (days) and the heat/wind thresholds that warn on an
    | outdoor session.
    */
    'weather' => [
        'horizon_days' => 7,
        'heat_c' => 25.0,
        'wind_kmh' => 30.0,
    ],

];
