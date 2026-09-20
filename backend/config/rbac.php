<?php

return [

    'cache_ttl' => (int) env('RBAC_CACHE_TTL', 3600),

    'super_admin_role_slug' => env('RBAC_SUPER_ADMIN_ROLE_SLUG', 'super-admin'),

    /**
     * Comma-separated emails that bypass permission checks (equivalent to super-admin).
     */
    'super_admin_emails' => array_values(array_filter(array_map(
        static fn (string $e): string => strtolower(trim($e)),
        explode(',', (string) env('RBAC_SUPER_ADMIN_EMAILS', ''))
    ))),

];
