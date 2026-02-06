<?php

namespace App\Services\Ghost;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Tags\Tag;

class GhostTagImporter
{
    protected int $created = 0;

    protected int $skipped = 0;

    protected array $errors = [];

    public function __construct(
        protected GhostImporter $importer
    ) {}

    public function import(bool $dryRun = false): array
    {
        $tags = $this->importer->getData('tags');
        $postsTags = $this->importer->getData('posts_tags');

        // Get only tags that are actually used in posts
        $usedTagIds = collect($postsTags)->pluck('tag_id')->unique()->toArray();

        foreach ($tags as $ghostTag) {
            // Skip unused tags
            if (! in_array($ghostTag['id'], $usedTagIds)) {
                continue;
            }

            try {
                $this->importTag($ghostTag, $dryRun);
            } catch (\Exception $e) {
                $this->errors[] = [
                    'tag' => $ghostTag['name'],
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to import Ghost tag', [
                    'tag' => $ghostTag['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }

    protected function importTag(array $ghostTag, bool $dryRun = false): void
    {
        // Check if tag already exists by slug
        $existingTag = Tag::query()
            ->where(DB::raw("slug->>'en'"), $ghostTag['slug'])
            ->first();

        if ($existingTag) {
            // Update type to 'post' if it's NULL
            if ($existingTag->type === null && ! $dryRun) {
                $existingTag->update(['type' => 'post']);
                Log::info('Updated existing tag type to post', ['name' => $ghostTag['name']]);
            }

            $this->skipped++;
            if (! $dryRun) {
                $this->importer->setMapping('tags', $ghostTag['id'], $existingTag->id);
            }
            Log::info('Tag already exists, skipping', ['name' => $ghostTag['name']]);

            return;
        }

        if ($dryRun) {
            // In dry run mode, just count as would-be-created
            $this->created++;
            Log::info('[DRY RUN] Would create tag', ['name' => $ghostTag['name']]);

            return;
        }

        // Create tag using Spatie Tags with 'post' type
        $tag = Tag::findOrCreate($ghostTag['name'], 'post');

        // Update slug to match Ghost slug
        $tag->update([
            'slug' => ['en' => $ghostTag['slug']],
        ]);

        // Store mapping
        $this->importer->setMapping('tags', $ghostTag['id'], $tag->id);

        $this->created++;

        Log::info('Tag imported successfully', [
            'ghost_id' => $ghostTag['id'],
            'pmone_id' => $tag->id,
            'name' => $ghostTag['name'],
        ]);
    }
}
