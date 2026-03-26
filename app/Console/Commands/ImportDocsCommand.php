<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class ImportDocsCommand extends Command
{
    protected $signature = 'docs:import {--fresh : Delete all existing docs posts before importing}';

    protected $description = 'Import markdown documentation files into the Posts system';

    public function handle(): int
    {
        $user = User::where('email', 'antonius@panoramamedia.co.id')->firstOrFail();
        $docsPath = base_path('frontend/content/docs');

        if (! File::isDirectory($docsPath)) {
            $this->error("Docs directory not found: {$docsPath}");

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $count = Post::where('settings->docs_audience', 'staff')
                ->orWhere('settings->docs_audience', 'exhibitor')
                ->count();

            if ($count > 0 && $this->confirm("Delete {$count} existing docs posts?")) {
                Post::where('settings->docs_audience', 'staff')
                    ->orWhere('settings->docs_audience', 'exhibitor')
                    ->forceDelete();
                $this->info("Deleted {$count} existing docs posts.");
            }
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $files = $this->getMarkdownFiles($docsPath);
        $this->info('Found '.count($files).' markdown files to import.');

        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $raw = File::get($file);
            $frontmatter = $this->parseFrontmatter($raw);
            $markdownContent = $this->extractContent($raw);

            if (! $frontmatter || empty($frontmatter['title'])) {
                $this->warn("Skipping {$file}: missing frontmatter or title");
                $skipped++;

                continue;
            }

            // Skip non-English
            if (($frontmatter['locale'] ?? 'en') !== 'en') {
                continue;
            }

            $audience = $frontmatter['audience'] ?? 'staff';
            $section = $frontmatter['section'] ?? 'general';
            $order = (int) ($frontmatter['order'] ?? 999);

            $slug = $audience.'-'.Str::slug(pathinfo($file, PATHINFO_FILENAME));

            // Skip if already exists
            if (Post::where('slug', $slug)->exists()) {
                $this->warn("Skipping '{$frontmatter['title']}': slug '{$slug}' already exists");
                $skipped++;

                continue;
            }

            // Remove <!-- VIDEO --> placeholder
            $markdownContent = preg_replace('/<!--\s*VIDEO\s*-->/', '', $markdownContent);

            // Convert markdown to HTML
            $html = (string) $converter->convert(trim($markdownContent));

            $post = Post::create([
                'title' => $frontmatter['title'],
                'slug' => $slug,
                'excerpt' => $frontmatter['description'] ?? null,
                'content' => $html,
                'content_format' => 'html',
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => now(),
                'settings' => [
                    'docs_section' => $section,
                    'docs_order' => $order,
                    'docs_audience' => $audience,
                ],
                'source' => 'native',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Attach author
            $post->authors()->attach($user->id, ['order' => 0]);

            // Attach tags
            $categoryTag = $this->mapToCategory($audience, $section);
            $post->syncPostTags(['docs', $categoryTag]);

            $imported++;
            $this->line("  Imported: {$frontmatter['title']} [{$slug}]");
        }

        $this->newLine();
        $this->info("Done! Imported: {$imported}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    private function getMarkdownFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            // Skip Chinese translations, templates, README
            if (str_contains($path, '/zh/')) {
                continue;
            }
            if (str_starts_with($file->getFilename(), '_')) {
                continue;
            }
            if ($file->getFilename() === 'README.md') {
                continue;
            }
            if ($file->getExtension() === 'md') {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    private function parseFrontmatter(string $content): ?array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
            return null;
        }

        $data = [];
        foreach (explode("\n", trim($matches[1])) as $line) {
            if (preg_match('/^(\w+):\s*"?(.*?)"?\s*$/', $line, $m)) {
                $data[$m[1]] = $m[2];
            }
        }

        return $data;
    }

    private function extractContent(string $raw): string
    {
        return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $raw);
    }

    private function mapToCategory(string $audience, string $section): string
    {
        if ($audience === 'staff' && $section === 'getting-started') {
            return 'docs-getting-started';
        }
        if ($audience === 'staff') {
            return 'docs-staff-guide';
        }

        return 'docs-exhibitor-guide';
    }
}
