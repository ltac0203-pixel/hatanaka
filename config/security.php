<?php

declare(strict_types=1);

return [
    'csp' => [
        'report_enabled' => env('SECURITY_CSP_REPORT_ENABLED', true),
        'report_uri' => env('SECURITY_CSP_REPORT_URI', '/api/security/csp-reports'),
    ],

    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => env('SECURITY_HSTS_PRELOAD', true),
    ],
];
