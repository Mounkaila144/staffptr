import { spawn, spawnSync } from 'node:child_process';
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { createServer, request as proxyRequest } from 'node:http';
import { extname, resolve } from 'node:path';
import { brotliCompressSync, constants, gzipSync } from 'node:zlib';

const phpBinary = process.env.PHP_BINARY
    ?? (process.platform === 'darwin' ? '/Applications/MAMP/bin/php/php8.3.30/bin/php' : 'php');
const database = resolve('database/e2e.sqlite');
const publicDirectory = resolve('public');
const environment = {
    ...process.env,
    APP_ENV: 'testing',
    CACHE_STORE: 'array',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    SESSION_DRIVER: 'database',
};

writeFileSync(database, '');

for (const argumentsList of [
    ['artisan', 'migrate:fresh', '--force'],
    ['artisan', 'db:seed', '--class=Database\\Seeders\\AuthenticationE2eSeeder', '--force'],
]) {
    const result = spawnSync(phpBinary, argumentsList, {
        cwd: process.cwd(),
        encoding: 'utf8',
        env: environment,
    });

    if (result.status !== 0) {
        throw new Error(`Impossible de préparer la base E2E.\n${result.stdout}\n${result.stderr}`);
    }
}

const phpServer = spawn(
    phpBinary,
    ['artisan', 'serve', '--host=127.0.0.1', '--port=8001', '--no-reload'],
    { cwd: process.cwd(), env: environment, stdio: 'inherit' },
);

const contentTypes = {
    '.css': 'text/css; charset=UTF-8',
    '.js': 'application/javascript; charset=UTF-8',
    '.json': 'application/json; charset=UTF-8',
    '.svg': 'image/svg+xml',
};
const compressedAssets = new Map();

function cacheAssets(directory) {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        const path = resolve(directory, entry.name);

        if (entry.isDirectory()) {
            cacheAssets(path);
        } else {
            const raw = readFileSync(path);
            compressedAssets.set(path, {
                raw,
                brotli: brotliCompressSync(raw, {
                    params: { [constants.BROTLI_PARAM_QUALITY]: 11 },
                }),
                gzip: gzipSync(raw, { level: 9 }),
            });
        }
    }
}

cacheAssets(resolve(publicDirectory, 'build'));

const server = createServer((incoming, outgoing) => {
    const pathname = new URL(incoming.url ?? '/', 'http://127.0.0.1').pathname;
    const assetPath = resolve(publicDirectory, `.${pathname}`);

    if (pathname.startsWith('/build/')
        && assetPath.startsWith(`${resolve(publicDirectory, 'build')}/`)) {
        const asset = compressedAssets.get(assetPath);

        if (asset) {
            const acceptedEncoding = incoming.headers['accept-encoding'] ?? '';
            const brotli = acceptedEncoding.includes('br');
            const gzip = ! brotli && acceptedEncoding.includes('gzip');
            const body = brotli ? asset.brotli : (gzip ? asset.gzip : asset.raw);
            outgoing.writeHead(200, {
                'Cache-Control': 'public, max-age=31536000, immutable',
                'Content-Type': contentTypes[extname(assetPath)] ?? 'application/octet-stream',
                ...(brotli ? { 'Content-Encoding': 'br', Vary: 'Accept-Encoding' } : {}),
                ...(gzip ? { 'Content-Encoding': 'gzip', Vary: 'Accept-Encoding' } : {}),
                'Content-Length': body.byteLength,
            });
            outgoing.end(body);

            return;
        }
    }

    const proxied = proxyRequest({
        hostname: '127.0.0.1',
        port: 8001,
        method: incoming.method,
        path: incoming.url,
        headers: incoming.headers,
    }, (response) => {
        outgoing.writeHead(response.statusCode ?? 500, response.headers);
        response.pipe(outgoing);
    });
    proxied.on('error', () => {
        outgoing.writeHead(503, { 'Content-Type': 'text/plain; charset=UTF-8' });
        outgoing.end('Serveur PHP en cours de démarrage.');
    });
    incoming.pipe(proxied);
});

server.listen(8000, '127.0.0.1');

function stop(signal) {
    server.close();
    phpServer.kill(signal);
}

process.on('SIGINT', () => stop('SIGINT'));
process.on('SIGTERM', () => stop('SIGTERM'));
phpServer.on('exit', (code) => process.exit(code ?? 1));
