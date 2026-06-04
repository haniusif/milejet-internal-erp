<?php

/*
 * Subdomain → module map for the MileJet apps.
 *
 * Each module's routes are bound to its host via Route::domain() in
 * routes/{portal,crm,fleet}.php. The HR module's routes stay
 * domain-agnostic (routes/web.php) so they answer on portal.* today and
 * on hr.* once that subdomain is repointed to this app.
 */

$base = env('APP_BASE_DOMAIN', 'milejet.space');

return [
    'base'   => $base,

    'portal' => env('DOMAIN_PORTAL', "portal.{$base}"),
    'hr'     => env('DOMAIN_HR',     "hr.{$base}"),
    'crm'    => env('DOMAIN_CRM',    "crm.{$base}"),
    'fleet'  => env('DOMAIN_FLEET',  "fleet.{$base}"),
    'finance' => env('DOMAIN_FINANCE', "finance.{$base}"),

    /*
     * Where the portal hub cards point today. As each subdomain is
     * repointed to this app, the default (its own https:// host) becomes
     * correct automatically; override via env during transition.
     * 'hr' defaults to null = link to this app's own dashboard, because
     * hr.milejet.space still serves the legacy Laravel 10 HR app.
     */
    'links' => [
        'hr'    => env('PORTAL_LINK_HR'),
        'crm'   => env('PORTAL_LINK_CRM',   "https://crm.{$base}"),
        'fleet' => env('PORTAL_LINK_FLEET', "http://fleets.{$base}"),
        'finance' => env('PORTAL_LINK_FINANCE', "https://finance.{$base}"),
        'erp'   => env('PORTAL_LINK_ERP',   "https://erp.{$base}"),
    ],
];
