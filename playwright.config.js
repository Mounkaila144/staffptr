import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const isCi = process.env.GITHUB_ACTIONS === 'true';
const e2eDatabase = resolve('database/e2e.sqlite');

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: isCi,
    retries: isCi ? 1 : 0,
    workers: 1,
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
        command: 'node tests/e2e/start-server.js',
        env: {
            ...process.env,
            APP_ENV: 'testing',
            APP_DEBUG: 'false',
            CACHE_STORE: 'array',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: e2eDatabase,
            SESSION_DRIVER: 'database',
        },
        url: 'http://127.0.0.1:8000/up',
        reuseExistingServer: !isCi,
        timeout: 120_000,
    },
});
