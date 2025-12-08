<?php

use App\Mail\ContactFormSubmitted;
use App\Models\ContactFormSubmission;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Include Fortify authentication routes
require __DIR__.'/auth.php';

// Development-only mail preview routes
if (app()->environment('local')) {
    Route::prefix('dev/mail')->group(function () {
        Route::get('/contact-form', function () {
            $project = Project::first();

            if (! $project) {
                return 'No project found. Please create a project first.';
            }

            // Create a fake submission for preview
            $submission = new ContactFormSubmission([
                'project_id' => $project->id,
                'subject' => 'New Contact Form Submission - '.$project->name,
                'form_data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '081234567890',
                    'company' => 'ACME Corporation',
                    'message' => "Hi,\n\nI'm interested in your services. Please contact me for more information.\n\nThank you!",
                ],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Preview Browser',
            ]);
            $submission->created_at = Carbon::now();
            $submission->setRelation('project', $project);

            return new ContactFormSubmitted($submission);
        });
    });
}
