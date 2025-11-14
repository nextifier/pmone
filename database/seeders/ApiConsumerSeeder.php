<?php

namespace Database\Seeders;

use App\Models\ApiConsumer;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApiConsumerSeeder extends Seeder
{
    private array $consumers = [
        [
            'name' => 'Main Website',
            'website_url' => 'https://example.com',
            'allowed_origins' => ['https://example.com', 'https://www.example.com'],
            'rate_limit' => 120,
        ],
        [
            'name' => 'Blog Website',
            'website_url' => 'https://blog.example.com',
            'allowed_origins' => ['https://blog.example.com'],
            'rate_limit' => 100,
        ],
        [
            'name' => 'News Portal',
            'website_url' => 'https://news.example.com',
            'allowed_origins' => ['https://news.example.com'],
            'rate_limit' => 150,
        ],
    ];

    public function run(): void
    {
        $this->command->info('Creating API consumers...');

        // Get a creator user
        $creator = User::role(['master', 'admin'])->first();

        if (! $creator) {
            $this->command->warn('No eligible user found to create API consumers. Skipping...');

            return;
        }

        $consumersCount = count($this->consumers);
        $bar = $this->command->getOutput()->createProgressBar($consumersCount);

        foreach ($this->consumers as $consumerData) {
            $consumer = ApiConsumer::create([
                'name' => $consumerData['name'],
                'website_url' => $consumerData['website_url'],
                'allowed_origins' => $consumerData['allowed_origins'],
                'rate_limit' => $consumerData['rate_limit'],
                'is_active' => true,
                'created_by' => $creator->id,
            ]);

            $this->command->newLine();
            $this->command->info("Created: {$consumer->name}");
            $this->command->info("API Key: {$consumer->api_key}");
            $this->command->info("Rate Limit: {$consumer->rate_limit} requests/minute");

            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✅ Successfully created $consumersCount API consumers!");
        $this->command->newLine();
        $this->command->warn('💡 Save the API keys above - they will be used to access the public blog API');
    }
}
