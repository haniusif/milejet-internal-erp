<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Geofenced attendance
    |--------------------------------------------------------------------------
    |
    | Mobile check-in / check-out can be restricted to within a radius of the
    | office location. Set the office coordinates and radius (in meters) below.
    | When "enforce" is true the API rejects punches made outside the radius.
    |
    */

    'office_lat' => env('ATTENDANCE_OFFICE_LAT'),
    'office_lng' => env('ATTENDANCE_OFFICE_LNG'),

    // Allowed radius around the office, in meters.
    'geofence_radius' => (int) env('ATTENDANCE_GEOFENCE_RADIUS', 300),

    // When true, server rejects check-in/out outside the radius (HTTP 422).
    'geofence_enforce' => filter_var(env('ATTENDANCE_GEOFENCE_ENFORCE', true), FILTER_VALIDATE_BOOL),
];
