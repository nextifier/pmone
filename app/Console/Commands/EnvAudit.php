<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deploy gate that keeps N brand deployments (pmone, monara, whitelabel)
 * from drifting apart. The UNCOMMENTED keys in .env.example are the
 * per-deployment manifest: every brand site must define them all. Run this
 * in the Forge deploy script before migrating; a missing key fails the
 * deploy instead of silently falling back to a config default.
 */
class EnvAudit extends Command
{
    protected $signature = 'env:audit
                            {--env-file=.env : The environment file to audit}
                            {--example=.env.example : The manifest file holding the required keys}';

    protected $description = 'Verify the deployment .env defines every key required by .env.example';

    public function handle(): int
    {
        $envPath = $this->resolvePath((string) $this->option('env-file'));
        $examplePath = $this->resolvePath((string) $this->option('example'));

        foreach (['env file' => $envPath, 'example file' => $examplePath] as $label => $path) {
            if (! is_file($path)) {
                $this->error("The {$label} [{$path}] does not exist.");

                return self::FAILURE;
            }
        }

        $envKeys = $this->parseKeys($envPath);
        $requiredKeys = $this->parseKeys($examplePath);
        $documentedKeys = $this->parseKeys($examplePath, includeCommented: true);

        $missing = array_values(array_diff($requiredKeys, $envKeys));
        $extra = array_values(array_diff($envKeys, $documentedKeys));

        if ($extra !== []) {
            $this->warn('Keys present in '.$this->option('env-file').' but not in the manifest (consider documenting them in '.$this->option('example').'):');

            foreach ($extra as $key) {
                $this->warn("  - {$key}");
            }
        }

        if ($missing !== []) {
            $this->error('Missing required keys in '.$this->option('env-file').':');

            foreach ($missing as $key) {
                $this->error("  - {$key}");
            }

            return self::FAILURE;
        }

        $this->info('env:audit passed - all '.count($requiredKeys).' manifest keys are defined.');

        return self::SUCCESS;
    }

    /**
     * Extract the KEY= names from an env-style file. Uncommented keys form
     * the required manifest; commented `# KEY=` lines count as documented
     * (optional) when $includeCommented is true.
     *
     * @return array<int, string>
     */
    protected function parseKeys(string $path, bool $includeCommented = false): array
    {
        $pattern = $includeCommented
            ? '/^\s*#?\s*([A-Z0-9_]+)\s*=/'
            : '/^\s*([A-Z0-9_]+)\s*=/';

        $keys = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match($pattern, $line, $matches) === 1) {
                $keys[] = $matches[1];
            }
        }

        return array_values(array_unique($keys));
    }

    protected function resolvePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }
}
