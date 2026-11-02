<?php

return [

    // Service account key downloaded from Google Cloud console (see README).
    'credentials' => base_path(env('GOOGLE_CREDENTIALS_PATH', 'storage/app/private/google/service-account.json')),

    // Time of the daily sync, server timezone (APP_TIMEZONE).
    'sync_time' => env('SHEETS_SYNC_TIME', '02:00'),

    'insert_chunk' => 500,

    // Tables that a mapping is never allowed to write into.
    'protected_tables' => [
        'spreadsheets', 'sheet_tabs', 'table_mappings', 'column_mappings', 'sync_logs',
        'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'migrations',
    ],

    // Columns added by the sync itself, can't be used as mapping targets.
    'reserved_columns' => ['id', 'table_mapping_id', 'synced_at', 'created_at', 'updated_at'],

];
