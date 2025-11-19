<?php

namespace App\Services\Ghost;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class GhostImporter
{
    protected array $data;

    protected array $mappings = [
        'users' => [],
        'tags' => [],
    ];

    protected string $mappingCachePath;

    public function __construct(
        protected string $jsonPath = 'storage/app/post-migration/ghost/ghost.ghost.2025-11-18-16-43-58.json'
    ) {
        $this->mappingCachePath = storage_path('app/post-migration/ghost/ghost_mappings.json');
        $this->loadData();
        $this->loadMappings();
    }

    protected function loadData(): void
    {
        $fullPath = base_path($this->jsonPath);

        if (! File::exists($fullPath)) {
            throw new \Exception("Ghost JSON file not found at: {$fullPath}");
        }

        $json = File::get($fullPath);
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Failed to decode Ghost JSON: '.json_last_error_msg());
        }

        $this->data = $decoded['db'][0]['data'] ?? [];

        Log::info('Ghost data loaded', [
            'posts' => count($this->data['posts'] ?? []),
            'users' => count($this->data['users'] ?? []),
            'tags' => count($this->data['tags'] ?? []),
        ]);
    }

    public function getData(?string $key = null): array
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? [];
    }

    protected function loadMappings(): void
    {
        if (File::exists($this->mappingCachePath)) {
            $json = File::get($this->mappingCachePath);
            $this->mappings = json_decode($json, true) ?? [
                'users' => [],
                'tags' => [],
            ];

            Log::info('Ghost mappings loaded from cache', [
                'users' => count($this->mappings['users'] ?? []),
                'tags' => count($this->mappings['tags'] ?? []),
            ]);
        }
    }

    protected function saveMappings(): void
    {
        File::put($this->mappingCachePath, json_encode($this->mappings, JSON_PRETTY_PRINT));

        Log::info('Ghost mappings saved to cache', [
            'users' => count($this->mappings['users'] ?? []),
            'tags' => count($this->mappings['tags'] ?? []),
        ]);
    }

    public function setMapping(string $type, string $ghostId, int $pmoneId): void
    {
        $this->mappings[$type][$ghostId] = $pmoneId;
        $this->saveMappings();
    }

    public function getMapping(string $type, string $ghostId): ?int
    {
        return $this->mappings[$type][$ghostId] ?? null;
    }

    public function getAllMappings(string $type): array
    {
        return $this->mappings[$type] ?? [];
    }

    public function clearMappings(): void
    {
        $this->mappings = [
            'users' => [],
            'tags' => [],
        ];

        if (File::exists($this->mappingCachePath)) {
            File::delete($this->mappingCachePath);
        }

        Log::info('Ghost mappings cleared');
    }
}
