<?php

namespace App\Imports\Concerns;

use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Events\BeforeImport;

trait TracksImportProgress
{
    protected ?string $importId = null;

    public function setImportId(string $importId): static
    {
        $this->importId = $importId;

        return $this;
    }

    public function initProgressTracking(BeforeImport $event): void
    {
        if (! $this->importId) {
            return;
        }

        $totalRows = 0;
        foreach ($event->getReader()->getTotalRows() as $sheetRows) {
            $totalRows += $sheetRows;
        }

        // Subtract 1 for header row
        $totalRows = max(0, $totalRows - 1);

        Cache::put("import:{$this->importId}", [
            'status' => 'processing',
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'imported_count' => 0,
            'percentage' => 0,
            'errors' => [],
            'error_message' => null,
        ], now()->addMinutes(30));
    }

    public function updateProgress(int $processed): void
    {
        if (! $this->importId) {
            return;
        }

        // Update every 10 rows to avoid excessive cache writes
        if ($processed % 10 !== 0) {
            return;
        }

        $data = Cache::get("import:{$this->importId}");

        if (! $data) {
            return;
        }

        $totalRows = $data['total_rows'];
        $percentage = $totalRows > 0 ? min(99, (int) round(($processed / $totalRows) * 100)) : 0;

        Cache::put("import:{$this->importId}", array_merge($data, [
            'processed_rows' => $processed,
            'percentage' => $percentage,
        ]), now()->addMinutes(30));
    }
}
