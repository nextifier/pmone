<?php

namespace App\Jobs;

use App\Exports\ContactsExport;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maatwebsite\Excel\Facades\Excel;

class ExportContacts implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public string $jobId,
        public array $filters,
        public string $sort,
    ) {}

    public function handle(): void
    {
        $this->initProgress(0, 'Preparing export...');

        $filename = 'contacts_'.now()->format('Y-m-d_His').'.xlsx';
        $path = "tmp/exports/{$this->jobId}.xlsx";

        $export = new ContactsExport($this->filters, $this->sort);

        Excel::store($export, $path, 'local');

        $this->completeProgress('Export completed', [
            'download_path' => $path,
            'download_filename' => $filename,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
