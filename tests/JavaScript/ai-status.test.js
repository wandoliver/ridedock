import test from 'node:test';
import assert from 'node:assert/strict';
import { hasFinishedAnalysis } from '../../resources/js/ai-status.js';

const elements = [{ dataset: { aiStatusUrl: '/ai-analyses/one/status' } }];
const response = (status) => async () => ({ ok: true, json: async () => ({ status }) });

test('pending and processing keep polling; completion and failure trigger refresh', async () => {
    for (const status of ['pending', 'processing']) {
        assert.equal(await hasFinishedAnalysis(elements, response(status)), false);
    }
    for (const status of ['completed', 'failed']) {
        assert.equal(await hasFinishedAnalysis(elements, response(status)), true);
    }
});

test('transient status errors do not trigger a page reload', async () => {
    await assert.rejects(hasFinishedAnalysis(elements, async () => ({ ok: false })));
});

test('one finished request refreshes a page with several outstanding requests', async () => {
    const multiple = [...elements, { dataset: { aiStatusUrl: '/ai-analyses/two/status' } }];
    assert.equal(await hasFinishedAnalysis(multiple, async (url, options) => {
        assert.equal(options.cache, 'no-store');
        return { ok: true, json: async () => ({ status: url.includes('two') ? 'completed' : 'pending' }) };
    }), true);
});
