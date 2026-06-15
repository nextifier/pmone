<?php

use App\Models\Event;
use App\Models\Project;
use App\Models\PromotionRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->project = Project::factory()->create();
});

function onsitePenaltyRule(Event $event): ?PromotionRule
{
    return PromotionRule::query()
        ->withTrashed()
        ->where('event_id', $event->id)
        ->where('kind', 'penalty')
        ->where('trigger_type', 'event_period')
        ->first();
}

it('creates a synced onsite penalty rule when an event is created with a rate', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'onsite_penalty_rate' => 40,
    ]);

    $rule = onsitePenaltyRule($event);

    expect($rule)->not->toBeNull();
    expect((float) $rule->value)->toBe(40.0);
    expect($rule->is_active)->toBeTrue();
    expect($rule->kind->value)->toBe('penalty');
    expect($rule->value_type->value)->toBe('percentage');
    expect($rule->trigger_config['phase'])->toBe('onsite');
    expect($rule->target_types)->toBe(['Order']);
});

it('does not create a rule for an event created without configuring an onsite rate', function () {
    // No explicit onsite_penalty_rate: the DB default applies but no onsite window is
    // configured, so there is nothing to charge yet and no rule should be created.
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
    ]);

    expect(onsitePenaltyRule($event))->toBeNull();
});

it('updates the rule value when the onsite penalty rate changes', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'onsite_penalty_rate' => 30,
    ]);

    $event->update(['onsite_penalty_rate' => 75]);

    $rule = onsitePenaltyRule($event);

    expect((float) $rule->value)->toBe(75.0);
    expect($rule->is_active)->toBeTrue();
    // Only one rule should ever exist for the event/onsite combo
    expect(onsitePenaltyRule($event)->id)->toBe($rule->id);
});

it('creates the rule lazily when a rate is first set on an existing event', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
    ]);

    expect(onsitePenaltyRule($event))->toBeNull();

    $event->update(['onsite_penalty_rate' => 50]);

    $rule = onsitePenaltyRule($event);
    expect($rule)->not->toBeNull();
    expect((float) $rule->value)->toBe(50.0);
    expect($rule->is_active)->toBeTrue();
});

it('deactivates the rule when the rate is set back to zero', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'onsite_penalty_rate' => 50,
    ]);

    $event->update(['onsite_penalty_rate' => 0]);

    $rule = onsitePenaltyRule($event);

    expect($rule)->not->toBeNull();
    expect((float) $rule->value)->toBe(0.0);
    expect($rule->is_active)->toBeFalse();
});

it('does not touch the rule when an unrelated field changes', function () {
    $event = Event::factory()->create([
        'project_id' => $this->project->id,
        'onsite_penalty_rate' => 25,
    ]);

    $rule = onsitePenaltyRule($event);
    $originalUpdatedAt = $rule->updated_at;

    $event->update(['title' => 'Renamed Event']);

    $rule->refresh();
    expect($rule->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});
