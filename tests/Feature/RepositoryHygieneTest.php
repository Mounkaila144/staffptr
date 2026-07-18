<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class RepositoryHygieneTest extends TestCase
{
    public function test_ac_8_sensitive_and_generated_paths_are_ignored(): void
    {
        $gitignore = $this->readFile('.gitignore');

        foreach (['/vendor', '/node_modules', '.env', '/storage/app/private/', '/database/database.sqlite', '/.ai/'] as $entry) {
            $this->assertStringContainsString($entry, $gitignore);
        }
    }

    public function test_ac_8_no_environment_file_is_tracked_by_git(): void
    {
        $process = new Process(['git', 'ls-files', '--error-unmatch', '.env'], base_path());
        $process->run();

        $this->assertFalse($process->isSuccessful(), 'Le fichier .env ne doit jamais être suivi par Git.');
    }

    public function test_ac_9_readme_documents_the_complete_clean_install(): void
    {
        $readme = $this->readFile('README.md');

        foreach ([
            'export PATH="/Applications/MAMP/bin/php/php8.3.30/bin:$PATH"',
            "alias composer='php /Applications/MAMP/bin/php/composer'",
            'composer install',
            'npm ci',
            'cp .env.example .env',
            'php artisan key:generate',
            'php artisan migrate',
            'npm run dev',
            'php artisan serve',
        ] as $instruction) {
            $this->assertStringContainsString($instruction, $readme);
        }
    }

    public function test_ac_10_example_environment_is_complete_and_secret_free(): void
    {
        $environment = $this->readFile('.env.example');

        foreach ([
            'APP_TIMEZONE=UTC',
            'APP_DISPLAY_TIMEZONE=Africa/Niamey',
            'APP_LOCALE=fr',
            'DB_CONNECTION=sqlite',
            'SESSION_DRIVER=file',
            'CACHE_STORE=file',
            'QUEUE_CONNECTION=sync',
            'REDIS_HOST=127.0.0.1',
        ] as $setting) {
            $this->assertStringContainsString($setting, $environment);
        }

        $this->assertSame('', $this->environmentValue($environment, 'APP_KEY'));
        $this->assertStringContainsString('# DB_PASSWORD=', $environment);
        $this->assertSame('null', $this->environmentValue($environment, 'REDIS_PASSWORD'));
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents(base_path($path));

        $this->assertNotFalse($contents);

        return $contents;
    }

    private function environmentValue(string $environment, string $key): ?string
    {
        preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $environment, $matches);

        return $matches[1] ?? null;
    }
}
