import { appendFileSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { brotliCompressSync, constants } from 'node:zlib';

const DEFAULT_LIMIT_KB = 300;

export function collectAssets(manifest, entries) {
    const assets = new Set();
    const visited = new Set();

    function visit(entry) {
        if (visited.has(entry)) {
            return;
        }

        const chunk = manifest[entry];

        if (!chunk) {
            throw new Error(`Entrée Vite introuvable : ${entry}`);
        }

        visited.add(entry);
        assets.add(chunk.file);

        for (const cssFile of chunk.css ?? []) {
            assets.add(cssFile);
        }

        for (const importedEntry of chunk.imports ?? []) {
            visit(importedEntry);
        }
    }

    for (const entry of entries) {
        visit(entry);
    }

    return [...assets].sort();
}

export function isWithinBudget(totalBytes, limitBytes) {
    return totalBytes <= limitBytes;
}

export function compressedSize(content) {
    return brotliCompressSync(content, {
        params: {
            [constants.BROTLI_PARAM_QUALITY]: 11,
        },
    }).byteLength;
}

function argumentValues(name) {
    return process.argv
        .slice(2)
        .filter((argument) => argument.startsWith(`--${name}=`))
        .map((argument) => argument.slice(name.length + 3));
}

function run() {
    const manifestPath = argumentValues('manifest')[0] ?? 'public/build/manifest.json';
    const entries = argumentValues('entry');
    const limitKb = Number(argumentValues('limit-kb')[0] ?? DEFAULT_LIMIT_KB);

    if (entries.length === 0 || !Number.isFinite(limitKb) || limitKb <= 0) {
        throw new Error('Les entrées Vite et une limite positive sont obligatoires.');
    }

    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const buildDirectory = dirname(manifestPath);
    const assets = collectAssets(manifest, entries);
    const measuredAssets = assets.map((asset) => ({
        asset,
        compressedBytes: compressedSize(readFileSync(resolve(buildDirectory, asset))),
    }));
    const totalBytes = measuredAssets.reduce((total, asset) => total + asset.compressedBytes, 0);
    const limitBytes = Math.round(limitKb * 1024);
    const passed = isWithinBudget(totalBytes, limitBytes);
    const measuredKb = (totalBytes / 1024).toFixed(2);
    const result = passed ? 'PASS' : 'FAIL';

    console.log(`Bundle Brotli : ${measuredKb} Ko / ${limitKb} Ko — ${result}`);

    for (const asset of measuredAssets) {
        console.log(`- ${asset.asset}: ${(asset.compressedBytes / 1024).toFixed(2)} Ko`);
    }

    if (process.env.GITHUB_STEP_SUMMARY) {
        appendFileSync(
            process.env.GITHUB_STEP_SUMMARY,
            `## Budget du bundle\n\n| Cible | Mesuré (Brotli) | Limite | Résultat |\n|---|---:|---:|---|\n| ${entries.join('<br>')} | ${measuredKb} Ko | ${limitKb} Ko | **${result}** |\n`,
        );
    }

    if (!passed) {
        process.exitCode = 1;
    }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    run();
}
