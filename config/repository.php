<?php

return [
    // Disable self-registration in production unless the institution explicitly enables it.
    'registration_enabled' => env('REGISTRATION_ENABLED', env('APP_ENV') !== 'production'),
    'require_verified_email' => env('REQUIRE_VERIFIED_EMAIL', env('APP_ENV') === 'production'),
    'remember_days' => 14,
    'mfa_enabled' => (bool) env('MFA_ENABLED', false),
    'mfa_roles' => ['admin', 'operator', 'verifier'],
    'fresh_password_minutes' => 5,
    'session_absolute_minutes' => (int) env('SESSION_ABSOLUTE_MINUTES', 480),
    'trusted_proxies' => array_filter(explode(',', env('TRUSTED_PROXIES', ''))),
    'documents_disk' => env('DOCUMENTS_DISK', 'local'),
    // Bound retained versions as well as active PDFs. Old files are never purged
    // automatically; exceeding a limit blocks future additions only.
    'document_max_files_per_work' => max(1, (int) env('DOCUMENT_MAX_FILES_PER_WORK', 50)),
    'document_max_versions_per_file' => max(1, (int) env('DOCUMENT_MAX_VERSIONS_PER_FILE', 20)),
    'document_max_storage_mb_per_work' => max(1, (int) env('DOCUMENT_MAX_STORAGE_MB_PER_WORK', 500)),
    'scan_required' => env('DOCUMENT_SCAN_REQUIRED', env('APP_ENV') === 'production'),
    'clamav_host' => env('CLAMAV_HOST', '127.0.0.1'),
    'clamav_port' => (int) env('CLAMAV_PORT', 3310),
    'clamav_timeout' => (int) env('CLAMAV_TIMEOUT', 15),
];
