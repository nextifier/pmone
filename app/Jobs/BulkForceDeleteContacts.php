<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BulkForceDeleteContacts implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $contactIds
     */
    public function __construct(
        public string $jobId,
        public array $contactIds,
    ) {}

    public function handle(): void
    {
        $this->initProgress(count($this->contactIds), 'Permanently deleting contacts...');

        $deleted = 0;

        foreach ($this->contactIds as $index => $id) {
            $contact = Contact::onlyTrashed()->find($id);

            if ($contact) {
                $contact->forceDelete();
                $deleted++;
            }

            $this->updateProgress($index + 1);
        }

        $this->completeProgress(
            "{$deleted} contact(s) permanently deleted",
            ['deleted_count' => $deleted],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
