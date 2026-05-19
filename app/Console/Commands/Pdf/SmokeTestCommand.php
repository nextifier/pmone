<?php

namespace App\Console\Commands\Pdf;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\LaravelPdf\Facades\Pdf;

#[Signature('pdf:smoke-test {--keep : Keep the generated PDF on disk for inspection}')]
#[Description('Render a minimal Browsershot PDF to verify Node + Chrome + laravel-pdf are wired up correctly. Use this after every deploy.')]
class SmokeTestCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Running PDF smoke test...');

        $this->reportEnvironment();

        $path = storage_path('app/pdf-smoke-test-'.now()->format('Ymd-His').'.pdf');
        $html = '<!doctype html><html><head><meta charset="utf-8"></head><body>'
            .'<h1 style="font-family:sans-serif">PM One PDF smoke test</h1>'
            .'<p>Rendered at <strong>'.now()->toIso8601String().'</strong></p>'
            .'</body></html>';

        $start = microtime(true);

        try {
            Pdf::html($html)->format('a4')->save($path);
        } catch (\Throwable $e) {
            $this->components->error('Render failed: '.$e->getMessage());
            $this->line('   File:  '.$e->getFile().':'.$e->getLine());
            $this->newLine();
            $this->showTroubleshootingHints($e);

            return self::FAILURE;
        }

        $elapsed = round((microtime(true) - $start) * 1000);
        $size = file_exists($path) ? filesize($path) : 0;

        if ($size < 1000) {
            $this->components->error("Render produced suspiciously small file ({$size} bytes). Expected ~5000+ bytes.");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Render time', "{$elapsed} ms");
        $this->components->twoColumnDetail('File size', number_format($size).' bytes');
        $this->components->twoColumnDetail('Output path', $path);

        if (! $this->option('keep')) {
            @unlink($path);
            $this->components->twoColumnDetail('Cleanup', 'PDF deleted (pass --keep to retain)');
        }

        $this->newLine();
        $this->components->info('PDF generation is working.');

        return self::SUCCESS;
    }

    protected function reportEnvironment(): void
    {
        $this->components->twoColumnDetail('Driver', config('laravel-pdf.driver'));
        $this->components->twoColumnDetail('Node binary', config('laravel-pdf.browsershot.node_binary') ?: '(auto-detect)');
        $this->components->twoColumnDetail('Chrome path', config('laravel-pdf.browsershot.chrome_path') ?: '(auto-detect)');
        $this->components->twoColumnDetail('No sandbox', config('laravel-pdf.browsershot.no_sandbox') ? 'true' : 'false');
        $this->newLine();
    }

    protected function showTroubleshootingHints(\Throwable $e): void
    {
        $message = strtolower($e->getMessage());

        $this->line('Troubleshooting:');

        if (str_contains($message, 'chrome') && str_contains($message, 'launch')) {
            $this->line('  - Chromium failed to launch. Common causes:');
            $this->line('    * System dependencies missing (libnss3, libasound, etc.)');
            $this->line('    * AppArmor sandbox on Ubuntu 23.10+ (see deployment notes)');
            $this->line('    * Try setting LARAVEL_PDF_NO_SANDBOX=true as a last resort');
        }

        if (str_contains($message, 'enoent') || str_contains($message, 'spawn')) {
            $this->line('  - Node or Chrome binary path is wrong.');
            $this->line('    * Verify LARAVEL_PDF_NODE_BINARY points to a real node executable');
            $this->line('    * Verify LARAVEL_PDF_CHROME_PATH points to an actual Chrome binary');
        }

        if (str_contains($message, 'timeout')) {
            $this->line('  - Render exceeded the configured timeout.');
            $this->line('    * Server may be overloaded; retry once it settles');
            $this->line('    * Increase timeout via Pdf::default()->withBrowsershot(...)');
        }

        if (str_contains($message, 'puppeteer')) {
            $this->line('  - Puppeteer not installed or missing Chrome.');
            $this->line('    * Run: sudo npm install -g puppeteer@^23');
            $this->line('    * Run: sudo npx puppeteer browsers install chrome');
        }
    }
}
