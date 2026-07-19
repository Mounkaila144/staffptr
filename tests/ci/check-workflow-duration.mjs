import { appendFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const DEFAULT_LIMIT_SECONDS = 600;

export function isWithinDuration(durationSeconds, limitSeconds = DEFAULT_LIMIT_SECONDS) {
    return durationSeconds < limitSeconds;
}

function elapsedSeconds(startedAt, completedAt = new Date()) {
    return Math.ceil((completedAt.getTime() - new Date(startedAt).getTime()) / 1000);
}

async function githubJson(path) {
    const response = await fetch(`${process.env.GITHUB_API_URL}${path}`, {
        headers: {
            Accept: 'application/vnd.github+json',
            Authorization: `Bearer ${process.env.GITHUB_TOKEN}`,
            'X-GitHub-Api-Version': '2026-03-10',
        },
    });

    if (!response.ok) {
        throw new Error(`GitHub API ${response.status} pour ${path}`);
    }

    return response.json();
}

async function run() {
    const requiredVariables = ['GITHUB_API_URL', 'GITHUB_REPOSITORY', 'GITHUB_RUN_ID', 'GITHUB_TOKEN'];

    for (const variable of requiredVariables) {
        if (!process.env[variable]) {
            throw new Error(`Variable CI manquante : ${variable}`);
        }
    }

    const runPath = `/repos/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID}`;
    const runData = await githubJson(runPath);
    const jobsData = await githubJson(`${runPath}/jobs?per_page=100`);
    const durationSeconds = elapsedSeconds(runData.run_started_at);
    const limitSeconds = Number(process.env.CI_DURATION_LIMIT_SECONDS ?? DEFAULT_LIMIT_SECONDS);
    const passed = isWithinDuration(durationSeconds, limitSeconds);
    const result = passed ? 'PASS' : 'FAIL';
    const completedJobs = jobsData.jobs.filter((job) => job.started_at && job.completed_at);
    const rows = completedJobs.map((job) => {
        const seconds = elapsedSeconds(job.started_at, new Date(job.completed_at));

        return `| ${job.name} | ${seconds} s | ${job.conclusion} |`;
    });

    const summary = [
        '## Durée de la chaîne',
        '',
        `Durée observable : **${durationSeconds} s** / seuil **${limitSeconds} s** — **${result}**`,
        '',
        '| Contrôle | Durée | Résultat |',
        '|---|---:|---|',
        ...rows,
        '',
    ].join('\n');

    console.log(`Durée CI : ${durationSeconds} s / ${limitSeconds} s — ${result}`);
    appendFileSync(process.env.GITHUB_STEP_SUMMARY, summary);

    if (!passed) {
        process.exitCode = 1;
    }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    await run();
}
