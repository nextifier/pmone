<?php

namespace Database\Factories;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventDocument;
use App\Models\Form;
use App\Models\Project;
use App\Support\PredefinedCustomFields;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fieldable_type' => Form::class,
            'fieldable_id' => Form::factory(),
            'context' => CustomField::CONTEXT_FORM,
            'type' => CustomField::TYPE_TEXT,
            'label' => ['en' => fake()->words(3, true)],
            'placeholder' => null,
            'help_text' => null,
            'options' => null,
            'validation' => ['required' => false],
            'settings' => [],
            'is_active' => true,
        ];
    }

    public function forForm(?Form $form = null): static
    {
        return $this->state(fn () => [
            'fieldable_type' => Form::class,
            'fieldable_id' => $form?->id ?? Form::factory(),
            'context' => CustomField::CONTEXT_FORM,
        ]);
    }

    public function businessMatching(?Event $event = null): static
    {
        return $this->state(fn () => [
            'fieldable_type' => Event::class,
            'fieldable_id' => $event?->id ?? Event::factory(),
            'context' => CustomField::CONTEXT_BUSINESS_MATCHING,
        ]);
    }

    public function ticketRegistration(?Event $event = null): static
    {
        return $this->state(fn () => [
            'fieldable_type' => Event::class,
            'fieldable_id' => $event?->id ?? Event::factory(),
            'context' => CustomField::CONTEXT_TICKET_REGISTRATION,
        ]);
    }

    public function brand(?Project $project = null): static
    {
        return $this->state(fn (array $attributes) => [
            'fieldable_type' => Project::class,
            'fieldable_id' => $project?->id ?? Project::factory(),
            'context' => CustomField::CONTEXT_BRAND,
            'key' => Str::snake(Str::ascii($attributes['label']['en'] ?? fake()->words(2, true))),
        ]);
    }

    public function document(?EventDocument $document = null): static
    {
        return $this->state(fn () => [
            'fieldable_type' => EventDocument::class,
            'fieldable_id' => $document?->id ?? EventDocument::factory(),
            'context' => CustomField::CONTEXT_DOCUMENT,
        ]);
    }

    public function predefined(string $context, string $systemKey): static
    {
        return $this->state(fn () => PredefinedCustomFields::attributesFor($context, $systemKey));
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation' => array_merge($attributes['validation'] ?? [], ['required' => true]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Configure the field as the given type with sensible per-type defaults.
     */
    public function type(string $type): static
    {
        return $this->state(fn () => array_merge(
            ['type' => $type],
            $this->defaultsForType($type)
        ));
    }

    private function defaultsForType(string $type): array
    {
        $options = [
            ['value' => 'option-1', 'label' => 'Option 1'],
            ['value' => 'option-2', 'label' => 'Option 2'],
            ['value' => 'option-3', 'label' => 'Option 3'],
        ];

        return match ($type) {
            CustomField::TYPE_SELECT,
            CustomField::TYPE_MULTI_SELECT,
            CustomField::TYPE_CHECKBOX_GROUP,
            CustomField::TYPE_RADIO => ['options' => $options],
            CustomField::TYPE_RATING => ['settings' => ['max' => 5]],
            CustomField::TYPE_LINEAR_SCALE => [
                'validation' => ['required' => false, 'min' => 1, 'max' => 5],
                'settings' => ['min_label' => 'Poor', 'max_label' => 'Excellent'],
            ],
            CustomField::TYPE_SLIDER => [
                'validation' => ['required' => false, 'min' => 0, 'max' => 100],
                'settings' => ['step' => 5],
            ],
            CustomField::TYPE_FILE => [
                'validation' => [
                    'required' => false,
                    'max_file_size' => 5120,
                    'allowed_file_types' => ['pdf', 'doc', 'docx'],
                ],
                'settings' => ['multiple' => false],
            ],
            CustomField::TYPE_SECTION => [
                'settings' => ['description' => '<p>'.fake()->sentence().'</p>'],
            ],
            default => [],
        };
    }
}
