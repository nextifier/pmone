<?php

use App\Http\Controllers\Api\LogController;
use App\Models\ApiConsumer;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\EventProductCategory;
use App\Models\Faq;
use App\Models\Form;
use App\Models\GaProperty;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\MediaCoverage;
use App\Models\PartnerCategory;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectBanner;
use App\Models\RundownItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Resolve the latest activity for a subject through the exact pipeline the
 * /api/logs endpoint uses (eager load + formatter), so the assertion exercises
 * getSubjectUrl + the morphWith relations.
 *
 * @return array<string, mixed>
 */
function formatLatestActivityFor(object $subject): array
{
    $latest = Activity::query()
        ->where('subject_type', $subject->getMorphClass())
        ->where('subject_id', $subject->getKey())
        ->latest('id')
        ->firstOrFail();

    $loaded = LogController::eagerLoadActivity(Activity::query()->whereKey($latest->id))->firstOrFail();

    return LogController::formatActivity($loaded);
}

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);

    $this->project = Project::factory()->create(['username' => 'acme']);
    $this->event = Event::factory()->create([
        'project_id' => $this->project->id,
        'slug' => 'expo-2026',
    ]);
});

it('links a logged subject to the correct admin page', function (Closure $make, string $expectedUrl) {
    $subject = $make($this);

    expect(formatLatestActivityFor($subject)['subject_url'])->toBe($expectedUrl);
})->with([
    'Hotel -> admin master detail' => [
        fn ($t) => Hotel::factory()->create(['slug' => 'grand-hotel']),
        '/hotels-master/grand-hotel',
    ],
    'Form -> top-level slug page' => [
        fn ($t) => Form::factory()->create(['slug' => 'contact-form', 'user_id' => $t->user->id]),
        '/forms/contact-form',
    ],
    'GaProperty -> web analytics' => [
        fn ($t) => GaProperty::factory()->create(['project_id' => $t->project->id]),
        '/web-analytics',
    ],
    'ProjectBanner -> project content banners' => [
        fn ($t) => ProjectBanner::factory()->create(['project_id' => $t->project->id]),
        '/projects/acme/content/banners',
    ],
    'CustomField (brand) -> project settings brand-fields' => [
        fn ($t) => tap(
            CustomField::factory()->brand($t->project)->create([
                'label' => ['en' => 'Phone'],
                'key' => 'phone',
            ]),
            fn ($field) => activity()->performedOn($field)->causedBy($t->user)->event('created')->log('created'),
        ),
        '/projects/acme/settings/brand-fields',
    ],
    'Faq -> event content faq tab' => [
        fn ($t) => Faq::factory()->create(['event_id' => $t->event->id]),
        '/projects/acme/events/expo-2026/content/faq',
    ],
    'Program -> event content programs tab' => [
        fn ($t) => Program::factory()->create(['event_id' => $t->event->id]),
        '/projects/acme/events/expo-2026/content/programs',
    ],
    'Guest -> event content guests tab' => [
        fn ($t) => Guest::factory()->create(['event_id' => $t->event->id]),
        '/projects/acme/events/expo-2026/content/guests',
    ],
    'RundownItem -> event content rundown tab' => [
        fn ($t) => RundownItem::factory()->create(['event_id' => $t->event->id]),
        '/projects/acme/events/expo-2026/content/rundown',
    ],
    'MediaCoverage -> event content media-coverage tab' => [
        fn ($t) => MediaCoverage::factory()->create(['event_id' => $t->event->id]),
        '/projects/acme/events/expo-2026/content/media-coverage',
    ],
    'EventProductCategory -> operational products' => [
        fn ($t) => EventProductCategory::factory()->create(['event_id' => $t->event->id]),
        '/projects/acme/events/expo-2026/operational/products',
    ],
    'PartnerCategory -> event content partners tab' => [
        fn ($t) => PartnerCategory::create(['event_id' => $t->event->id, 'name' => 'Sponsors']),
        '/projects/acme/events/expo-2026/content/partners',
    ],
    'EventDocument -> operational order-form-settings' => [
        fn ($t) => EventDocument::factory()->create(['event_id' => $t->event->id]),
        '/projects/acme/events/expo-2026/operational/order-form-settings',
    ],
    'HotelEvent -> event hotel detail' => [
        fn ($t) => HotelEvent::create([
            'hotel_id' => Hotel::factory()->create(['slug' => 'grand-hotel'])->id,
            'event_id' => $t->event->id,
            'is_active' => true,
        ]),
        '/projects/acme/events/expo-2026/hotels/grand-hotel',
    ],
]);

it('builds the ApiConsumer link from its id', function () {
    $consumer = ApiConsumer::factory()->create();

    expect(formatLatestActivityFor($consumer)['subject_url'])->toBe("/api-consumers/{$consumer->id}/edit");
});

it('injects project_id so event-scoped models surface in the per-project feed', function () {
    $category = EventProductCategory::factory()->create(['event_id' => $this->event->id]);

    $activity = Activity::query()
        ->where('subject_type', $category->getMorphClass())
        ->where('subject_id', $category->id)
        ->where('event', 'created')
        ->firstOrFail();

    expect($activity->properties['project_id'] ?? null)->toBe($this->project->id);
});

it('resolves legacy ProjectCustomField activity rows without crashing', function () {
    // Rows written before the centralized custom-fields migration keep the
    // retired FQCN and an id that no longer matches custom_fields; the link
    // must still build from the project_id stored in properties.
    $activity = Activity::query()->create([
        'log_name' => 'default',
        'description' => 'updated',
        'event' => 'updated',
        'subject_type' => 'App\\Models\\ProjectCustomField',
        'subject_id' => 999999,
        'causer_type' => $this->user->getMorphClass(),
        'causer_id' => $this->user->id,
        'properties' => [
            'project_id' => $this->project->id,
            'attributes' => ['label' => 'Phone'],
        ],
    ]);

    $loaded = LogController::eagerLoadActivity(Activity::query()->whereKey($activity->id))->firstOrFail();
    $formatted = LogController::formatActivity($loaded);

    expect($formatted['subject_url'])->toBe('/projects/acme/settings/brand-fields')
        ->and($formatted['subject_name'])->toBe('Phone');
});

it('injects a projects own id so it appears in its own activity feed', function () {
    $activity = Activity::query()
        ->where('subject_type', $this->project->getMorphClass())
        ->where('subject_id', $this->project->id)
        ->where('event', 'created')
        ->firstOrFail();

    expect($activity->properties['project_id'] ?? null)->toBe($this->project->id);
});

it('describes a self registration as registering a new account', function () {
    $newUser = User::factory()->create();

    activity()
        ->performedOn($newUser)
        ->causedBy($newUser)
        ->event('registered')
        ->log('User registered');

    $activity = Activity::query()->where('event', 'registered')->latest('id')->firstOrFail();
    $loaded = LogController::eagerLoadActivity(Activity::query()->whereKey($activity->id))->firstOrFail();

    expect(LogController::formatActivity($loaded)['human_description'])
        ->toBe("{$newUser->name} registered a new account");
});

it('humanizes the model type in bulk delete descriptions', function () {
    activity()
        ->causedBy($this->user)
        ->event('bulk_deleted')
        ->withProperties(['deleted_count' => 3, 'model_type' => 'ApiConsumer'])
        ->log('');

    $activity = Activity::query()->where('event', 'bulk_deleted')->latest('id')->firstOrFail();
    $formatted = LogController::formatActivity($activity);

    expect($formatted['human_description'])->toContain('API consumer(s)')
        ->and($formatted['human_description'])->not->toContain('ApiConsumer');
});

it('describes a payment gateway connection test', function () {
    activity()
        ->causedBy($this->user)
        ->event('payment_gateway_test_connection')
        ->withProperties(['provider' => 'midtrans', 'mode' => 'sandbox', 'success' => true])
        ->log('');

    $activity = Activity::query()->where('event', 'payment_gateway_test_connection')->latest('id')->firstOrFail();

    expect(LogController::formatActivity($activity)['human_description'])
        ->toBe("{$this->user->name} tested midtrans (sandbox) connection - success");
});
