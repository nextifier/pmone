<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MyTickets\UpdateVisitorProfileRequest;
use App\Http\Resources\AttendeeResource;
use App\Http\Resources\EventCustomFieldResource;
use App\Http\Resources\PublicTicketOrderResource;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\FieldResponse;
use App\Models\Project;
use App\Models\TicketOrder;
use App\Models\User;
use App\Support\BusinessMatchingValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MyTicketsController extends Controller
{
    /**
     * Orders this user bought (Manage Attendees view).
     */
    public function orders(Request $request): JsonResponse
    {
        $orders = TicketOrder::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'attendees.ticket', 'event'])
            ->latest()
            ->get();

        return response()->json(['data' => PublicTicketOrderResource::collection($orders)]);
    }

    /**
     * Tickets this user holds (claimed), across all events (My Tickets).
     */
    public function tickets(Request $request): JsonResponse
    {
        $attendees = Attendee::query()
            ->where('claimed_by_user_id', $request->user()->id)
            ->with(['ticket', 'ticketOrderItem.ticketOrder.event.project.links'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $attendees->map(function (Attendee $a) {
                $event = $a->ticketOrderItem?->ticketOrder?->event;

                return [
                    'attendee' => (new AttendeeResource($a))->resolve(),
                    'event' => $event ? [
                        'id' => $event->id,
                        'slug' => $event->slug,
                        'title' => $event->title,
                        // The shareable e-ticket lives on the event's own website.
                        'website_url' => $this->resolveWebsiteUrl($event->project),
                    ] : null,
                ];
            }),
        ]);
    }

    private function resolveWebsiteUrl(?Project $project): ?string
    {
        return $project?->links
            ->first(fn ($link) => strtolower((string) $link->label) === 'website')
            ?->url;
    }

    /**
     * The buyer (or claimant, or staff) edits an attendee's on-ticket details.
     */
    public function updateAttendee(Request $request, string $ulid): JsonResponse
    {
        $attendee = Attendee::query()
            ->where('ulid', $ulid)
            ->with('ticketOrderItem.ticketOrder')
            ->firstOrFail();

        $user = $request->user();
        $order = $attendee->ticketOrderItem?->ticketOrder;
        $isStaff = $user->hasAnyRole(['staff', 'admin', 'master']);
        $isOwner = $order?->user_id === $user->id || $attendee->claimed_by_user_id === $user->id;

        abort_unless($isOwner || $isStaff, 403);

        if ($attendee->checked_in_at !== null && ! $isStaff) {
            return response()->json(['message' => 'This ticket is checked in and is now staff-managed.'], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $attendee->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? $attendee->email,
            'phone' => $validated['phone'] ?? $attendee->phone,
            'personalized_at' => $attendee->personalized_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Attendee updated.',
            'data' => new AttendeeResource($attendee->fresh('ticket')),
        ]);
    }

    /**
     * Optional visitor profile + completeness meter.
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presentProfile($request->user())]);
    }

    public function updateProfile(UpdateVisitorProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return response()->json([
            'message' => 'Profile updated.',
            'data' => $this->presentProfile($user->fresh()),
        ]);
    }

    /**
     * The user's business-matching answers for an event (intake from the dashboard).
     */
    public function fieldResponses(Request $request, Event $event): JsonResponse
    {
        $fields = $event->business_matching_enabled
            ? $event->eventCustomFields()->where('is_active', true)->orderBy('order_column')->get()
            : collect();
        $responses = FieldResponse::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('event_custom_field_id', $fields->pluck('id'))
            ->get()
            ->keyBy('event_custom_field_id');

        return response()->json([
            'data' => [
                'opt_in' => (bool) $request->user()->business_matching_opt_in,
                'fields' => EventCustomFieldResource::collection($fields),
                'responses' => $fields->mapWithKeys(fn ($f) => [$f->id => $responses->get($f->id)?->value]),
            ],
        ]);
    }

    public function saveFieldResponses(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'opt_in' => ['sometimes', 'boolean'],
            'responses' => ['sometimes', 'array'],
            'responses.*.custom_field_id' => ['required_with:responses', 'integer'],
            'responses.*.value' => ['nullable'],
        ]);

        $user = $request->user();

        $optIn = array_key_exists('opt_in', $validated)
            ? (bool) $validated['opt_in']
            : (bool) $user->business_matching_opt_in;

        $errors = BusinessMatchingValidation::errorsFor(
            $event,
            $optIn,
            $validated['responses'] ?? [],
            'responses',
        );
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if (array_key_exists('opt_in', $validated)) {
            $user->forceFill(['business_matching_opt_in' => (bool) $validated['opt_in']])->save();
        }

        $fieldIds = $event->business_matching_enabled
            ? $event->eventCustomFields()->where('is_active', true)->pluck('id')
            : collect();
        foreach ($validated['responses'] ?? [] as $resp) {
            $fieldId = (int) $resp['custom_field_id'];
            if (! $fieldIds->contains($fieldId)) {
                continue;
            }
            $value = $resp['value'] ?? null;
            FieldResponse::query()->updateOrCreate(
                ['user_id' => $user->id, 'event_custom_field_id' => $fieldId],
                ['value' => is_array($value) ? $value : [$value]],
            );
        }

        return response()->json(['message' => 'Business matching answers saved.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProfile(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'birth_date' => $user->birth_date?->toDateString(),
            'birth_year' => $user->birth_year,
            'country' => $user->country,
            'city' => $user->city,
            'company_name' => $user->company_name,
            'profession' => $user->profession,
            'position' => $user->position,
            'business_matching_opt_in' => (bool) $user->business_matching_opt_in,
            'profile_completeness' => $user->profile_completeness,
        ];
    }
}
