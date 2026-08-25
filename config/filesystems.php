<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
        |----------------------------------------------------------------
        | Image persistence hotfix -- 'public' and 'private' now point
        | at Supabase Storage (S3-compatible) instead of Render's local
        | disk. Deliberately kept the SAME disk names the whole app
        | already uses ('public', 'private') rather than introducing new
        | ones -- MediaController, School::mediaUrl(), Student::photo_
        | url(), the logo/signature/photo upload controllers, and
        | ReportCardPdfService all call Storage::disk('public'|'private')
        | by name and don't need to change at all. Only where those
        | bytes physically live changes.
        |
        | Every path stored in the database (logo_path, signature_path,
        | photo_path -- e.g. "school-logos/xyz.png") is disk-relative,
        | not an absolute local path, so no database migration is
        | needed either -- the exact same path strings resolve correctly
        | against the new disk.
        |----------------------------------------------------------------
        */

        'public' => [
            'driver' => 's3',
            'key' => env('SUPABASE_S3_ACCESS_KEY_ID'),
            'secret' => env('SUPABASE_S3_SECRET_ACCESS_KEY'),
            'region' => env('SUPABASE_S3_REGION', 'eu-west-1'),
            'bucket' => env('SUPABASE_S3_PUBLIC_BUCKET', 'skulag-images-sign'),
            'endpoint' => env('SUPABASE_S3_ENDPOINT'),
            // Supabase's S3-compatible endpoint requires path-style
            // (bucket-in-path) rather than AWS's default virtual-hosted
            // (bucket-as-subdomain) style. This is documented by
            // Supabase, not something Laravel/AWS decides -- leaving
            // this false against Supabase silently produces wrong URLs.
            'use_path_style_endpoint' => true,
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 's3',
            'key' => env('SUPABASE_S3_ACCESS_KEY_ID'),
            'secret' => env('SUPABASE_S3_SECRET_ACCESS_KEY'),
            'region' => env('SUPABASE_S3_REGION', 'eu-west-1'),
            'bucket' => env('SUPABASE_S3_PRIVATE_BUCKET', 'skulag-photos'),
            'endpoint' => env('SUPABASE_S3_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        // Old Render-local paths, kept reachable ONLY as the source side
        // of the one-time `php artisan storage:migrate-to-supabase`
        // command (see app/Console/Commands/MigrateStorageToSupabase.php).
        // Safe to leave defined permanently -- nothing else in the app
        // references these disk names, and they cost nothing if unused.
        // Do not delete the underlying files until you've confirmed the
        // migration succeeded and things have been stable for a while.
        'legacy_public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'throw' => false,
            'report' => false,
        ],

        'legacy_private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
