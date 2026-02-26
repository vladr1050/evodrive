#!/usr/bin/env node
/**
 * E2E test runner: migrates, seeds, starts Laravel server, runs Playwright, stops server.
 * Uses database/e2e.sqlite and port 8010.
 */

import { spawn } from 'child_process';
import { mkdir } from 'fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const headed = process.argv.includes('--headed');

async function run(cmd, args, opts = {}) {
  return new Promise((resolve, reject) => {
    const p = spawn(cmd, args, {
      cwd: root,
      stdio: 'inherit',
      env: {
        ...process.env,
        APP_ENV: 'testing',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: path.join(root, 'database', 'e2e.sqlite'),
        ...opts.env,
      },
      ...opts,
    });
    p.on('close', (code) => (code === 0 ? resolve() : reject(new Error(`Exit ${code}`))));
  });
}

async function main() {
  await mkdir(path.join(root, 'storage', 'test-reports'), { recursive: true });
  await mkdir(path.join(root, 'database'), { recursive: true });

  // Create empty sqlite file if not exists
  const dbPath = path.join(root, 'database', 'e2e.sqlite');
  const { existsSync, writeFileSync } = await import('fs');
  if (!existsSync(dbPath)) {
    writeFileSync(dbPath, '');
  }

  const env = {
    ...process.env,
    APP_ENV: 'testing',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbPath,
  };

  console.log('Running migrations (fresh)...');
  await run('php', ['artisan', 'migrate:fresh', '--force'], { env });

  console.log('Seeding E2E data...');
  await run('php', ['artisan', 'db:seed', '--class=E2eSeeder', '--force'], { env });

  console.log('Starting Laravel server on port 8010...');
  const server = spawn('php', ['artisan', 'serve', '--port=8010'], {
    cwd: root,
    stdio: 'pipe',
    env: { ...env, APP_ENV: 'local', APP_DEBUG: 'true' },
  });

  let serverReady = false;
  server.stdout?.on('data', (d) => {
    const s = d.toString();
    if (s.includes('8010') || s.includes('Development')) serverReady = true;
  });

  await new Promise((r) => setTimeout(r, 3000));

  try {
    console.log('Running Playwright...');
    const pwArgs = ['test', '--project=chromium'];
    if (headed) pwArgs.push('--headed');

    await run('npx', ['playwright', ...pwArgs], {
      env: { ...process.env, CI: '1' },
    });
  } finally {
    server.kill('SIGTERM');
    await new Promise((r) => setTimeout(r, 500));
  }

  console.log('E2E tests completed.');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
