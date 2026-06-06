<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Original image optimization
    |--------------------------------------------------------------------------
    |
    | User-uploaded images are downscaled (never upscaled) + compressed BEFORE
    | being stored as the Media Library "original", so huge camera/phone uploads
    | don't waste disk. Conversions (lqip/sm/md/lg/xl) are still generated as usual.
    |
    | `original_max_dimension` MUST be >= the largest conversion width in any model
    | so conversions never upscale (largest conversion in this app is 1920).
    |
    */

    'original_max_dimension' => (int) env('IMAGE_ORIGINAL_MAX', 1920),

    'original_quality' => (int) env('IMAGE_ORIGINAL_QUALITY', 82),

    // Skip optimizing files already within the cap AND below this size (bytes).
    'optimize_min_bytes' => (int) env('IMAGE_OPTIMIZE_MIN_BYTES', 512000), // 500 KB
];
