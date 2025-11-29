<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $posts = Post::where('source', 'ghost')
            ->where('content', 'like', '%srcset="%')
            ->get();

        $fixed = 0;

        foreach ($posts as $post) {
            $originalContent = $post->content;
            $newContent = $this->fixSrcsetAttributes($originalContent);

            if ($originalContent !== $newContent) {
                $post->content = $newContent;
                $post->saveQuietly();
                $fixed++;
            }
        }

        Log::info("Fixed {$fixed} Ghost posts with broken srcset attributes");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot be reversed - srcset data is already lost
    }

    private function fixSrcsetAttributes(string $content): string
    {
        return preg_replace_callback(
            '/<img\s+([^>]*?)src="([^"]+)"([^>]*?)srcset="([^"]+)"([^>]*?)>/i',
            function ($matches) {
                $beforeSrc = $matches[1];
                $src = $matches[2];
                $betweenSrcAndSrcset = $matches[3];
                $srcset = $matches[4];
                $afterSrcset = $matches[5];

                // Check if srcset contains relative paths (doesn't start with http)
                if (! preg_match('/^https?:\/\//', $srcset)) {
                    // Remove broken srcset
                    return "<img {$beforeSrc}src=\"{$src}\"{$betweenSrcAndSrcset}{$afterSrcset}>";
                }

                return $matches[0];
            },
            $content
        );
    }
};
