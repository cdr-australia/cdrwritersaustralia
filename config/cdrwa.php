<?php

return [

    // Design paths: templates, assets and registry live in Git under site/
    'site_path'     => env('CDRWA_SITE_PATH'),
    'template_path' => env('CDRWA_TEMPLATE_PATH'),
    'assets_path'   => env('CDRWA_ASSETS_PATH'),
    'static_path'   => env('CDRWA_STATIC_PATH'),
    'registry_path' => env('CDRWA_REGISTRY_PATH'),

    // Seed content (starting JSON, in Git)
    'seed_data_path' => env('CDRWA_SEED_DATA_PATH'),

    // Live content and backups (outside Git)
    'data_path'   => env('CDRWA_DATA_PATH'),
    'backup_path' => env('CDRWA_BACKUP_PATH'),

    // Where the builder writes the public static site
    'output_path' => env('CDRWA_OUTPUT_PATH'),

    'site_url' => env('CDRWA_SITE_URL'),

    // Used only by the database seeder
    'admin_email'    => env('CDRWA_ADMIN_EMAIL'),
    'admin_password' => env('CDRWA_ADMIN_PASSWORD'),

];