import { defineConfig, devices } from '@playwright/test';

const phpBinary = process.env.PHP_BINARY
    ?? (process.platform === 'darwin' ? '/Applications/MAMP/bin/php/php8.3.30/bin/php' : 'php');
const isCi = process.env.GITHUB_ACTIONS === 'true';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: isCi,
    retries: isCi ? 1 : 0,
    workers: isCi ? 1 : undefined,
    reporter: isCi ? [['github'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: `${phpBinary} artisan serve --host=127.0.0.1 --port=8000 --no-reload`,
        env: {
            ...process.env,
            APP_ENV: 'testing',
            APP_DEBUG: 'false',
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'array',
        },
        url: 'http://127.0.0.1:8000/up',
        reuseExistingServer: !isCi,
        timeout: 120_000,
    },
});
