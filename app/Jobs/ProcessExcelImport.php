<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessExcelImport implements ShouldQueue
{
    use Queueable;

    /**
     * No retry - partial data is worse than failing.
     */
    public int $tries = 1;

    /**
     * 10 minutes max.
     */
    public int $timeout = 600;

    public function __construct(
        public string $importId,
        public string $filePath,
        public string $importClass,
        public string $tempFolder,
    ) {}

    public function handle(): void
    {
        try {
            /** @var \App\Imports\Concerns\TracksImportProgress $import */
            $import = new $this->importClass;
            $import->setImportId($this->importId);

            Excel::import($import, $this->filePath);

            $errors = [];
            if (method_exists($import, 'getFailures')) {
                foreach ($import->getFailures() as $failure) {
                    $errors[] = [
                        'row' => $failure->row(),
                        'attribute' => $failure->attribute(),
                        'errors' => $failure->errors(),
                        'values' => $failure->values(),
                    ];
                }
            }

            $importedCount = method_exists($import, 'getImportedCount')
                ? $import->getImportedCount()
                : 0;

            $this->updateCache([
                'status' => 'completed',
                'processed_rows' => Cache::get("import:{$this->importId}")['total_rows'] ?? 0,
                'imported_count' => $importedCount,
                'percentage' => 100,
                'errors' => $errors,
            ]);

            Log::info('Excel import completed', [
                'import_id' => $this->importId,
                'imported_count' => $importedCount,
                'error_count' => count($errors),
            ]);
        } catch (Throwable $e) {
            Log::error('Excel import failed', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
            ]);

            $this->updateCache([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            Storage::disk('local')->deleteDirectory("tmp/uploads/{$this->tempFolder}");
        }
    }

    protected function updateCache(array $updates): void
    {
        $data = Cache::get("import:{$this->importId}", [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported_count' => 0,
            'percentage' => 0,
            'errors' => [],
            'error_message' => null,
        ]);

        Cache::put("import:{$this->importId}", array_merge($data, $updates), now()->addMinutes(30));
    }
}
