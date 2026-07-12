<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WebsitePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsitePage>
 */
class WebsitePageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = '<p>'.fake()->paragraph().'</p>';

        return [
            'project_id' => Project::factory(),
            'key' => fake()->randomElement(WebsitePage::KEYS),
            'body' => ['en' => $body, 'id' => $body],
        ];
    }
}
