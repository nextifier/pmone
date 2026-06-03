<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SDF storage disk
    |--------------------------------------------------------------------------
    |
    | Disk used to store generated shader SDF (.bin) files. These must be served
    | over a public, CORS-enabled URL because browsers fetch them cross-origin as
    | a shader `shapeSdfUrl`. Defaults to the Media Library disk (local: `public`,
    | production: `r2`); set SHADER_SDF_DISK to override.
    |
    */

    'sdf_disk' => env('SHADER_SDF_DISK', env('MEDIA_DISK', 'public')),

    'sdf_directory' => 'shaders/sdf',

];
