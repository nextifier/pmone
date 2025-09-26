<?php

// Production MediaLibrary Configuration Fix
// Add these to your production .env file:

/*
MEDIA_DISK=s3
IMAGE_DRIVER=gd
QUEUE_CONNECTION=database
QUEUE_CONVERSIONS_BY_DEFAULT=true
QUEUE_CONVERSIONS_AFTER_DB_COMMIT=true

# For debugging - disable queue conversions temporarily
# QUEUE_CONVERSIONS_BY_DEFAULT=false

# AWS S3 Settings (if using S3)
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=https://your-bucket.s3.amazonaws.com

# For Cloudflare R2 (if using R2)
AWS_ENDPOINT=https://your-account.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
*/

return [
    // Additional configuration for production debugging
    'conversion_disk' => env('MEDIA_DISK', 'public'), // Ensure conversions use same disk

    // Debugging options
    'log_conversions' => env('LOG_MEDIA_CONVERSIONS', false),
    'conversion_timeout' => env('MEDIA_CONVERSION_TIMEOUT', 300), // 5 minutes
];