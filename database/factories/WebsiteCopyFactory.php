<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebsiteCopy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteCopy>
 */
class WebsiteCopyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->sentence();

        return [
            'project_id' => Project::factory(),
            'key' => WebsiteCopy::keyFor(
                fake()->randomElement(WebsiteCopy::PAGE_KEYS),
                fake()->randomElement(WebsiteCopy::FIELDS),
            ),
            'value' => ['en' => $text, 'id' => $text],
        ];
    }
}
