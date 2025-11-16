<?php

namespace App\Http\Controllers\Api;

use App\Actions\ContactForm\SubmitContactFormAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContactFormSubmitRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ContactFormController extends Controller
{
    /**
     * Submit a contact form from external websites.
     */
    public function submit(ContactFormSubmitRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Find project by username
            $project = Project::where('username', $validated['project_username'])->first();

            if (! $project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found.',
                ], 404);
            }

            // Execute submission action
            $action = new SubmitContactFormAction(
                project: $project,
                formData: $validated['data'],
                subject: $validated['subject'] ?? null,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $submission = $action->execute();

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully. We will get back to you soon.',
                'submission_id' => $submission->ulid,
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'project_username' => $request->input('project_username'),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
