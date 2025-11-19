<?php

namespace App\Services\Canvas;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CanvasImporter
{
    protected array $posts = [];

    protected array $tags = [];

    protected array $topics = [];

    protected array $postsTags = [];

    protected array $postsTopics = [];

    protected string $canvasDataPath;

    public function __construct()
    {
        $this->canvasDataPath = storage_path('app/post-migration/canvas');
        $this->loadData();
    }

    protected function loadData(): void
    {
        // Load posts
        $postsPath = $this->canvasDataPath.'/canvas_posts.json';
        if (! File::exists($postsPath)) {
            throw new \Exception("Canvas posts file not found at: {$postsPath}");
        }
        $this->posts = json_decode(File::get($postsPath), true);

        // Load tags
        $tagsPath = $this->canvasDataPath.'/canvas_tags.json';
        if (! File::exists($tagsPath)) {
            throw new \Exception("Canvas tags file not found at: {$tagsPath}");
        }
        $this->tags = json_decode(File::get($tagsPath), true);

        // Load topics
        $topicsPath = $this->canvasDataPath.'/canvas_topics.json';
        if (! File::exists($topicsPath)) {
            throw new \Exception("Canvas topics file not found at: {$topicsPath}");
        }
        $this->topics = json_decode(File::get($topicsPath), true);

        // Load posts_tags relationships
        $postsTagsPath = $this->canvasDataPath.'/canvas_posts_tags.json';
        if (! File::exists($postsTagsPath)) {
            throw new \Exception("Canvas posts_tags file not found at: {$postsTagsPath}");
        }
        $this->postsTags = json_decode(File::get($postsTagsPath), true);

        // Load posts_topics relationships
        $postsTopicsPath = $this->canvasDataPath.'/canvas_posts_topics.json';
        if (! File::exists($postsTopicsPath)) {
            throw new \Exception("Canvas posts_topics file not found at: {$postsTopicsPath}");
        }
        $this->postsTopics = json_decode(File::get($postsTopicsPath), true);

        Log::info('Canvas data loaded', [
            'posts' => count($this->posts),
            'tags' => count($this->tags),
            'topics' => count($this->topics),
            'posts_tags' => count($this->postsTags),
            'posts_topics' => count($this->postsTopics),
        ]);
    }

    public function getPosts(): array
    {
        return $this->posts;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getTopics(): array
    {
        return $this->topics;
    }

    public function getPostsTags(): array
    {
        return $this->postsTags;
    }

    public function getPostsTopics(): array
    {
        return $this->postsTopics;
    }

    public function getTagsForPost(string $postId): array
    {
        // Get tag IDs for this post
        $tagIds = array_map(
            fn ($pt) => $pt['tag_id'],
            array_filter($this->postsTags, fn ($pt) => $pt['post_id'] === $postId)
        );

        // Get tag objects
        return array_filter($this->tags, fn ($tag) => in_array($tag['id'], $tagIds));
    }

    public function getTopicsForPost(string $postId): array
    {
        // Get topic IDs for this post
        $topicIds = array_map(
            fn ($pt) => $pt['topic_id'],
            array_filter($this->postsTopics, fn ($pt) => $pt['post_id'] === $postId)
        );

        // Get topic objects
        return array_filter($this->topics, fn ($topic) => in_array($topic['id'], $topicIds));
    }
}
