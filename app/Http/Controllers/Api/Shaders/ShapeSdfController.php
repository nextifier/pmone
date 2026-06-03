<?php

namespace App\Http\Controllers\Api\Shaders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shaders\ConvertShapeSdfRequest;
use App\Services\Shaders\SvgToSdfGenerator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ShapeSdfController extends Controller
{
    public function __construct(private readonly SvgToSdfGenerator $generator) {}

    /**
     * List the stored SDF files (newest first).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        return response()->json(['data' => $this->listFiles()]);
    }

    /**
     * Convert an uploaded SVG/PNG logo to a 512x512 SDF .bin, store it on the
     * configured disk, and return its public URL for use as a shader `shapeSdfUrl`.
     */
    public function store(ConvertShapeSdfRequest $request): JsonResponse
    {
        @set_time_limit(60);

        $file = $request->file('file');
        $isSvg = strtolower((string) $file->getClientOriginalExtension()) === 'svg';
        $mime = $isSvg ? 'image/svg+xml' : 'image/png';

        try {
            $binary = $this->generator->generate((string) $file->get(), $mime);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not convert this file. Make sure it is a valid SVG or PNG.'], 422);
        }

        $base = Str::slug((string) pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'shape';
        $path = $this->directory().'/'.$base.'-'.substr(Str::uuid()->toString(), 0, 8).'.bin';

        $this->disk()->put($path, $binary);

        return response()->json($this->fileResource($path, strlen($binary)));
    }

    /**
     * Delete a single stored SDF file.
     */
    public function destroy(Request $request, string $filename): JsonResponse
    {
        $this->authorizeRole($request);

        $path = $this->resolvePath($filename);
        abort_unless($path !== null && $this->disk()->exists($path), 404);

        $this->disk()->delete($path);

        return response()->json(['message' => 'File deleted.']);
    }

    /**
     * Delete multiple stored SDF files at once.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validate([
            'filenames' => ['required', 'array', 'min:1'],
            'filenames.*' => ['string'],
        ]);

        $deleted = 0;
        foreach ($validated['filenames'] as $name) {
            $path = $this->resolvePath($name);
            if ($path !== null && $this->disk()->exists($path)) {
                $this->disk()->delete($path);
                $deleted++;
            }
        }

        return response()->json(['deleted' => $deleted]);
    }

    /**
     * Publicly stream a stored SDF .bin THROUGH Laravel so the CORS middleware adds
     * the cross-origin headers shaders need (static /storage files bypass the kernel
     * and get no CORS, which blocks the shader's `fetch(url, {mode:'cors'})`).
     */
    public function serve(string $filename): StreamedResponse
    {
        $path = $this->resolvePath($filename);
        abort_unless($path !== null && $this->disk()->exists($path), 404);

        return $this->disk()->response($path, $filename, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function authorizeRole(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['master', 'admin']) ?? false, 403);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('shaders.sdf_disk'));
    }

    private function directory(): string
    {
        return (string) config('shaders.sdf_directory', 'shaders/sdf');
    }

    /**
     * @return array<int, array{filename: string, url: string, bytes: int, modified_at: int}>
     */
    private function listFiles(): array
    {
        $disk = $this->disk();
        $files = [];
        foreach ($disk->files($this->directory()) as $path) {
            if (! str_ends_with($path, '.bin')) {
                continue;
            }
            $files[] = $this->fileResource($path, $disk->size($path));
        }
        usort($files, fn (array $a, array $b): int => $b['modified_at'] <=> $a['modified_at']);

        return $files;
    }

    /**
     * @return array{filename: string, url: string, bytes: int, modified_at: int}
     */
    private function fileResource(string $path, int $bytes): array
    {
        $disk = $this->disk();

        return [
            'filename' => basename($path),
            'url' => route('shaders.sdf.serve', ['filename' => basename($path)]),
            'bytes' => $bytes,
            'modified_at' => $disk->lastModified($path),
        ];
    }

    /**
     * Resolve a safe disk path from an untrusted filename (no traversal, .bin only).
     */
    private function resolvePath(string $filename): ?string
    {
        $base = basename($filename);
        if (! preg_match('/^[A-Za-z0-9._-]+\.bin$/', $base)) {
            return null;
        }

        return $this->directory().'/'.$base;
    }
}
