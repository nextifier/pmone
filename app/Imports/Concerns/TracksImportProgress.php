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

        // Only count rows from the first sheet
        $sheetRows = $event->getReader()->getTotalRows();
        $totalRows = reset($sheetRows) ?: 0;

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

        $data = Cache::get("import:{$this->importId}");

        if (! $data) {
            return;
        }

        $totalRows = $data['total_rows'];

        // Dynamic throttle: every item for small sets, every 5 for medium, every 10 for large
        $interval = match (true) {
            $totalRows <= 20 => 1,
            $totalRows <= 100 => 5,
            default => 10,
        };

        if ($processed % $interval !== 0) {
            return;
        }
        $percentage = $totalRows > 0 ? min(99, (int) round(($processed / $totalRows) * 100)) : 0;

        Cache::put("import:{$this->importId}", array_merge($data, [
            'processed_rows' => $processed,
            'percentage' => $percentage,
        ]), now()->addMinutes(30));
    }
}
