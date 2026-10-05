import { execFileSync } from 'node:child_process';
import { lstatSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath, pathToFileURL, URL } from 'node:url';

// Report paths and rule names only; never echo potentially sensitive contents.
export function checkRepositoryEntry(path, contents = Buffer.alloc(0)) {
    const findings = [];
    const name = path.split('/').at(-1);
    if ((name.startsWith('.env') && name !== '.env.example') || name === 'auth.json') {
        findings.push('environment or credential file');
    }
    if (/\.(fit|gpx|tcx|kml|kmz|sqlite3?|db|sql|pem|p12|pfx)$/i.test(path)) {
        findings.push('private activity, database or key file');
    }
    const fixture = /^(tests\/|database\/(factories|seeders)\/)/.test(path);
    if (fixture && (/\.(png|jpe?g|webp|gif|pdf|zip|gz|bin)$/i.test(path) || contents.includes(0))) {
        findings.push('binary fixture needs replacement with a synthetic generator');
    }
    if (!contents.includes(0)) {
        const text = contents.toString('utf8');
        if (/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(text)
            || /\bgh[pousr]_[A-Za-z0-9]{30,}\b/.test(text)
            || /\bgithub_pat_[A-Za-z0-9_]{30,}\b/.test(text)
            || /\bAKIA[0-9A-Z]{16}\b/.test(text)
            || /\bsk-(?:proj-)?[A-Za-z0-9_-]{40,}\b/.test(text)) {
            findings.push('recognizable secret pattern');
        }
    }
    return findings;
}

function main() {
    const root = resolve(fileURLToPath(new URL('..', import.meta.url)));
    const paths = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '-z'], { cwd: root })
        .toString('utf8').split('\0').filter(Boolean);
    const findings = [];
    for (const path of new Set(paths)) {
        const fullPath = resolve(root, path);
        const metadata = lstatSync(fullPath, { throwIfNoEntry: false });
        if (!metadata) continue; // A tracked file may have been deleted locally.
        if (metadata.isSymbolicLink()) {
            findings.push(`${path}: repository symlink requires review`);
            continue;
        }
        const contents = readFileSync(fullPath);
        for (const rule of checkRepositoryEntry(path, contents)) findings.push(`${path}: ${rule}`);
    }
    if (findings.length > 0) {
        console.error(findings.join('\n'));
        process.exitCode = 1;
    } else {
        console.log('Repository asset and secret-pattern checks passed.');
    }
}

if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url) main();
