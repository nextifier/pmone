<?php

namespace App\Exports;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PostsExport extends BaseExport
{
    protected function getQuery(): Builder
    {
        return Post::query()
            ->with([
                'creator:id,name,email,username',
                'authors:id,name,email,username',
                'tags' => function ($query) {
                    $query->where('type', 'post');
                },
                'categories',
                'media',
            ])
            ->withCount(['visits', 'media']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'ULID',
            'Title',
            'Slug',
            'Excerpt',
            'Content (Preview)',
            'Content',
            'Content Format',
            'Status',
            'Visibility',
            'Featured',
            'Published At',
            'Reading Time (min)',
            'Meta Title',
            'Meta Description',
            'Source',
            'Creator',
            'Authors',
            'Tags',
            'Categories',
            'Visits Count',
            'Media Count',
            'Featured Image URL',
            'OG Image URL',
            'Created At',
            'Updated At',
        ];
    }

    /**
     * @param  Post  $post
     */
    public function map($post): array
    {
        // Get featured image URL
        $featuredImage = $post->hasMedia('featured_image')
            ? $post->getFirstMediaUrl('featured_image', 'original')
            : '-';

        // Get OG image URL
        $ogImage = $post->hasMedia('og_image')
            ? $post->getFirstMediaUrl('og_image', 'original')
            : '-';

        // Format creator
        $creator = $post->creator
            ? $post->creator->name
            : '-';

        // Format authors
        $authors = $post->authors->isNotEmpty()
            ? $post->authors->pluck('name')->join(', ')
            : '-';

        // Format tags
        $tags = $post->tags->isNotEmpty()
            ? $post->tags->pluck('name')->join(', ')
            : '-';

        // Format categories
        $categories = $post->categories->isNotEmpty()
            ? $post->categories->pluck('name')->join(', ')
            : '-';

        // Truncate content to 500 characters for preview
        $contentPreview = $post->content
            ? Str::limit(strip_tags($post->content), 500)
            : '-';

        return [
            $post->id,
            $post->ulid ?? '-',
            $post->title,
            $post->slug,
            $post->excerpt ?? '-',
            $contentPreview,
            $post->content ?? '-',
            $this->titleCase($post->content_format),
            $this->titleCase($post->status),
            $this->titleCase($post->visibility),
            $post->featured ? 'Yes' : 'No',
            $post->published_at?->format('Y-m-d H:i:s') ?? '-',
            $post->reading_time ?? '-',
            $post->meta_title ?? '-',
            $post->meta_description ?? '-',
            $this->titleCase($post->source),
            $creator,
            $authors,
            $tags,
            $categories,
            $post->visits_count ?? 0,
            $post->media_count ?? 0,
            $featuredImage,
            $ogImage,
            $post->created_at?->format('Y-m-d H:i:s'),
            $post->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    protected function applyFilters(Builder $query): void
    {
        // Search filter (uses Post::search scope)
        if (isset($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        // Status filter
        if (isset($this->filters['status'])) {
            $statuses = array_map('trim', explode(',', $this->filters['status']));
            $statuses = array_filter($statuses);

            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } elseif (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            }
        }

        // Visibility filter
        if (isset($this->filters['visibility'])) {
            $visibilities = array_map('trim', explode(',', $this->filters['visibility']));
            $visibilities = array_filter($visibilities);

            if (count($visibilities) > 1) {
                $query->whereIn('visibility', $visibilities);
            } elseif (count($visibilities) === 1) {
                $query->where('visibility', $visibilities[0]);
            }
        }

        // Featured filter
        if (isset($this->filters['featured'])) {
            $query->where('featured', filter_var($this->filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        // Creator filter (supports multiple IDs and 'none')
        if (isset($this->filters['creator'])) {
            $creators = array_map('trim', explode(',', $this->filters['creator']));
            $creators = array_filter($creators);

            $hasNone = in_array('none', $creators);
            $creatorIds = array_filter($creators, fn ($c) => $c !== 'none');

            if ($hasNone && count($creatorIds) > 0) {
                $query->where(function ($q) use ($creatorIds) {
                    $q->whereNull('created_by')
                        ->orWhereIn('created_by', $creatorIds);
                });
            } elseif ($hasNone) {
                $query->whereNull('created_by');
            } elseif (count($creatorIds) > 1) {
                $query->whereIn('created_by', $creatorIds);
            } elseif (count($creatorIds) === 1) {
                $query->where('created_by', $creatorIds[0]);
            }
        }

        // Source filter
        if (isset($this->filters['source'])) {
            $sources = array_map('trim', explode(',', $this->filters['source']));
            $sources = array_filter($sources);

            if (count($sources) > 1) {
                $query->whereIn('source', $sources);
            } elseif (count($sources) === 1) {
                $query->where('source', $sources[0]);
            }
        }
    }

    protected function applySorting(Builder $query): void
    {
        [$field, $direction] = $this->parseSortField($this->sort);

        if (in_array($field, ['title', 'status', 'published_at', 'created_at', 'updated_at', 'visits_count', 'media_count'])) {
            $query->orderBy($field, $direction);
        } elseif ($field === 'creator') {
            $query->leftJoin('users', 'posts.created_by', '=', 'users.id')
                ->orderBy('users.name', $direction)
                ->select('posts.*');
        } else {
            $query->orderBy('published_at', 'desc');
        }
    }
}
