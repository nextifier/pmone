<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BulkSoftDeleteContacts implements ShouldQueue
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
        public ?int $deletedBy = null,
    ) {}

    public function handle(): void
    {
        $this->initProgress(count($this->contactIds), 'Deleting contacts...');

        $deleted = 0;

        foreach ($this->contactIds as $index => $id) {
            $contact = Contact::find($id);

            if ($contact) {
                // Set deleted_by manually since auth() is not available in queue
                if ($this->deletedBy) {
                    $contact->deleted_by = $this->deletedBy;
                    $contact->saveQuietly();
                }

                $contact->delete();
                $deleted++;
            }

            $this->updateProgress($index + 1);
        }

        $this->completeProgress(
            "{$deleted} contact(s) deleted",
            ['deleted_count' => $deleted],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
