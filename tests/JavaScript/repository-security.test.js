import assert from 'node:assert/strict';
import test from 'node:test';
import { checkRepositoryEntry } from '../../scripts/check-repository.mjs';

test('rejects private source files, database dumps and environment backups', () => {
    for (const path of ['tests/ride.fit', 'tests/route.gpx', 'database/copy.sqlite', 'backup.sql', '.env.staging', 'auth.json']) {
        assert.ok(checkRepositoryEntry(path).length > 0, path);
    }
});

test('rejects screenshots and embedded binaries in fixtures while allowing product artwork', () => {
    assert.ok(checkRepositoryEntry('tests/screenshot.png').length > 0);
    assert.ok(checkRepositoryEntry('tests/encoded.dat', Buffer.from([0, 1, 2])).length > 0);
    assert.deepEqual(checkRepositoryEntry('public/logo.png', Buffer.from([0, 1, 2])), []);
});

test('detects recognizable credentials without reporting their values', () => {
    const secret = ['ghp_', 'x'.repeat(36)].join('');
    const result = checkRepositoryEntry('app/example.php', Buffer.from(secret));
    assert.deepEqual(result, ['recognizable secret pattern']);
    assert.ok(!JSON.stringify(result).includes(secret));
});

test('allows synthetic fixture source, documented dummy values and the environment template', () => {
    assert.deepEqual(checkRepositoryEntry('tests/example.php', Buffer.from("'access_token' => 'fake-access-token'")), []);
    assert.deepEqual(checkRepositoryEntry('.env.example', Buffer.from('OPENAI_API_KEY=\n')), []);
});
