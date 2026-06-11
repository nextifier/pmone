<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'type' => FormField::TYPE_TEXT,
            'label' => fake()->words(3, true),
            'placeholder' => null,
            'help_text' => null,
            'options' => null,
            'validation' => ['required' => false],
            'settings' => [],
        ];
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation' => array_merge($attributes['validation'] ?? [], ['required' => true]),
        ]);
    }

    /**
     * Configure the field as the given type with sensible per-type defaults.
     */
    public function type(string $type): static
    {
        return $this->state(fn (array $attributes) => array_merge(
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
            FormField::TYPE_SELECT,
            FormField::TYPE_MULTI_SELECT,
            FormField::TYPE_CHECKBOX_GROUP,
            FormField::TYPE_RADIO => ['options' => $options],
            FormField::TYPE_RATING => ['settings' => ['max' => 5]],
            FormField::TYPE_LINEAR_SCALE => [
                'validation' => ['required' => false, 'min' => 1, 'max' => 5],
                'settings' => ['min_label' => 'Poor', 'max_label' => 'Excellent'],
            ],
            FormField::TYPE_SLIDER => [
                'validation' => ['required' => false, 'min' => 0, 'max' => 100],
                'settings' => ['step' => 5],
            ],
            FormField::TYPE_FILE => [
                'validation' => [
                    'required' => false,
                    'max_file_size' => 5120,
                    'allowed_file_types' => ['pdf', 'doc', 'docx'],
                ],
                'settings' => ['multiple' => false],
            ],
            FormField::TYPE_SECTION => [
                'settings' => ['description' => '<p>'.fake()->sentence().'</p>'],
            ],
            default => [],
        };
    }
}
