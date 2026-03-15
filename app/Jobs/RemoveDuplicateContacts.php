<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Traits\TracksJobProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RemoveDuplicateContacts implements ShouldQueue
{
    use Queueable, TracksJobProgress;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<int, array{keep: array, duplicates: array}>  $duplicateGroups
     */
    public function __construct(
        public string $jobId,
        public array $duplicateGroups,
    ) {}

    public function handle(): void
    {
        $totalDuplicates = array_reduce(
            $this->duplicateGroups,
            fn (int $carry, array $group) => $carry + count($group['duplicates']),
            0,
        );

        $this->initProgress($totalDuplicates, 'Removing duplicate contacts...');

        $removedCount = 0;

        DB::transaction(function () use (&$removedCount) {
            foreach ($this->duplicateGroups as $group) {
                $keep = Contact::find($group['keep']['id']);

                if (! $keep) {
                    continue;
                }

                $mergedEmails = $keep->emails ?? [];
                $mergedPhones = $keep->phones ?? [];
                $keepProjectIds = $keep->projects()->pluck('projects.id')->toArray();
                $keepTagNames = $keep->tagsWithType('contact_tag')->pluck('name')->toArray();

                foreach ($group['duplicates'] as $dupData) {
                    $duplicate = Contact::find($dupData['id']);

                    if (! $duplicate) {
                        continue;
                    }

                    // Merge emails
                    foreach ($duplicate->emails ?? [] as $email) {
                        if (! in_array($email, $mergedEmails)) {
                            $mergedEmails[] = $email;
                        }
                    }

                    // Merge phones
                    foreach ($duplicate->phones ?? [] as $phone) {
                        if (! in_array($phone, $mergedPhones)) {
                            $mergedPhones[] = $phone;
                        }
                    }

                    // Collect project IDs
                    $dupProjectIds = $duplicate->projects()->pluck('projects.id')->toArray();
                    $keepProjectIds = array_unique(array_merge($keepProjectIds, $dupProjectIds));

                    // Collect tags
                    $dupTagNames = $duplicate->tagsWithType('contact_tag')->pluck('name')->toArray();
                    $keepTagNames = array_unique(array_merge($keepTagNames, $dupTagNames));

                    $duplicate->delete();
                    $removedCount++;

                    $this->updateProgress($removedCount);
                }

                // Update kept contact with merged data
                $keep->update([
                    'emails' => $mergedEmails,
                    'phones' => $mergedPhones,
                ]);

                $keep->projects()->syncWithoutDetaching($keepProjectIds);

                if (! empty($keepTagNames)) {
                    $keep->syncContactTags($keepTagNames);
                }
            }
        });

        $this->completeProgress(
            "{$removedCount} duplicate contact(s) removed",
            ['removed_count' => $removedCount],
        );
    }

    public function failed(\Throwable $exception): void
    {
        $this->failProgress($exception->getMessage());
    }
}
