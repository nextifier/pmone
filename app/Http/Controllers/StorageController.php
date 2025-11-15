<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageController extends Controller
{
    /**
     * Serve a file from public storage.
     *
     * This controller serves storage files through Laravel to enable CORS headers
     * via HandleCors middleware. This is necessary because files served via symlink
     * bypass Laravel's middleware stack.
     *
     * In production, consider:
     * - Serving files via CDN with proper CORS configuration
     * - Configuring CORS at the web server level (nginx/Apache)
     * - Using signed URLs for sensitive files
     */
    public function serve(Request $request, string $path): BinaryFileResponse
    {
        $filePath = storage_path('app/public/'.$path);

        abort_if(! file_exists($filePath), 404);

        $response = response()->file($filePath);

        // Add CORS headers to allow PDF export from frontend
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');

        return $response;
    }
}
