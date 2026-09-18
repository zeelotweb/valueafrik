<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Temporary File Upload Endpoint Configuration
    |---------------------------------------------------------------------------
    |
    | Livewire handles file uploads by storing uploads in a temporary directory
    | before the file is stored permanently. All file uploads are directed to
    | a global endpoint for temporary storage. This global endpoint has a
    | separate validation rule from your own component's — the one place
    | that actually matters here is `rules`: it defaults to `max:12288`
    | (12MB), which silently caps every upload at 12MB regardless of the
    | app's own larger `max:20480` validation on 'photos.*'/'photo' — the
    | component's rule never even gets a chance to run past this one.
    | The rest of these are the package's own unpublished defaults,
    | reproduced here in full because Livewire's config merge only
    | merges at the top level, not into this nested array.
    |
    */

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'), // Example: 'local', 's3' | Default: 'default'
        'rules' => ['required', 'file', 'max:20480'], // 20MB, matching every upload surface's own validation
        'directory' => null, // Example: 'tmp' | Default: 'livewire-tmp'
        'middleware' => null, // Example: 'throttle:5,1' | Default: 'throttle:60,1'
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
