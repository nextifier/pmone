<?php

namespace App\Console\Commands;

use App\Helpers\PhoneCountryHelper;
use App\Models\Contact;
use App\Models\ContactFormSubmission;
use Illuminate\Console\Command;

class BackfillPhoneCountry extends Command
{
    protected $signature = 'contacts:backfill-country {--submissions : Backfill contact form submissions too}';

    protected $description = 'Backfill country from phone number prefix for existing contacts and submissions';

    public function handle(): int
    {
        $this->backfillContacts();

        if ($this->option('submissions')) {
            $this->backfillSubmissions();
        }

        $this->newLine();
        $this->info('Done!');

        return Command::SUCCESS;
    }

    protected function backfillContacts(): void
    {
        $this->info('Backfilling contacts...');

        $contacts = Contact::whereNotNull('phones')->get();
        $bar = $this->output->createProgressBar($contacts->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;

        foreach ($contacts as $contact) {
            $address = $contact->address;
            $phones = $contact->phones;

            // Skip if country already set
            if (! empty($address['country'])) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // Skip if no phones
            if (empty($phones) || ! is_array($phones)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // Normalize phones and detect country
            $normalizedPhones = array_map([PhoneCountryHelper::class, 'normalizePhoneNumber'], $phones);
            $country = PhoneCountryHelper::getCountryName($normalizedPhones[0]);

            if ($country) {
                $address = is_array($address) ? $address : [];
                $address['country'] = $country;
                $contact->address = $address;
                $contact->phones = $normalizedPhones;
                $contact->saveQuietly();
                $updated++;
            } else {
                // Still normalize the phones even if country not detected
                if ($normalizedPhones !== $phones) {
                    $contact->phones = $normalizedPhones;
                    $contact->saveQuietly();
                }
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Updated: {$updated}");
        $this->comment("  Skipped: {$skipped}");
    }

    protected function backfillSubmissions(): void
    {
        $this->info('Backfilling contact form submissions...');

        $submissions = ContactFormSubmission::all();
        $bar = $this->output->createProgressBar($submissions->count());
        $bar->start();

        $updated = 0;
        $skipped = 0;

        foreach ($submissions as $submission) {
            $formData = $submission->form_data;

            // Skip if no phone or country already set
            if (empty($formData['phone']) || ! empty($formData['country'])) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $formData['phone'] = PhoneCountryHelper::normalizePhoneNumber($formData['phone']);
            $country = PhoneCountryHelper::getCountryName($formData['phone']);

            if ($country) {
                $formData['country'] = $country;
                $submission->form_data = $formData;
                $submission->saveQuietly();
                $updated++;
            } else {
                // Still save normalized phone
                $submission->form_data = $formData;
                $submission->saveQuietly();
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  Updated: {$updated}");
        $this->comment("  Skipped: {$skipped}");
    }
}
