import assert from 'node:assert/strict';
import test from 'node:test';
import FitParser from 'fit-file-parser';
import { normalizeFitData } from '../../resources/js/fit/normalize-fit.js';
import { shiftingFit } from '../Fixtures/shifting-fit.js';

const diagnostic = (data) => normalizeFitData(data).parser.shift_diagnostics;

test('CRC-valid gear field descriptions alone do not prove a recorded gear sample', async () => {
    const parsed = await new FitParser({ force: false, mode: 'list' }).parseAsync(shiftingFit({ gearDescriptionsOnly: true }));
    assert.equal(parsed.field_descriptions.length, 2);
    assert.deepEqual(diagnostic(parsed), {
        status: 'described_only', fields_described: true, shift_messages: 0, gear_value_messages: 0,
    });
});

test('real decoded native and mapped developer shifts report availability', async () => {
    for (const developerFields of [false, true]) {
        const parsed = await new FitParser({ force: false, mode: 'list' }).parseAsync(shiftingFit({ developerFields }));
        assert.equal(diagnostic(parsed).status, 'available');
        assert.equal(diagnostic(parsed).shift_messages, 4);
    }
});

test('record-only and other message gear values are unsupported rather than called absent', () => {
    const value = diagnostic({ records: [{ front_gear: 2, rear_gear: 7 }] });
    assert.equal(value.status, 'unsupported');
    assert.equal(value.gear_value_messages, 1);
    const other = diagnostic({ messages: { synthetic_unknown_message: [{ rear_gear: 7 }] } });
    assert.equal(other.status, 'unsupported');
    assert.equal(other.gear_value_messages, 1);
});

test('incomplete shift messages are distinct from absent data', () => {
    assert.equal(diagnostic({ events: [{ event: 'rear_gear_change' }] }).status, 'incomplete');
    assert.equal(diagnostic({ events: [{ event: 'timer' }] }).status, 'absent');
    for (const value of [0, null, undefined, -1, NaN, '7', 0xffffffff]) {
        assert.equal(diagnostic({ records: [{ rear_gear: value }] }).status, 'absent');
    }
});

test('explicit native developer descriptions identify samples without inventing shifts', () => {
    const data = {
        field_descriptions: [{ native_mesg_num: 21, native_field_num: 11, field_name: 'synthetic_gear_index' }],
        records: [{ synthetic_gear_index: 7 }],
    };
    assert.equal(diagnostic(data).status, 'unsupported');
    assert.equal(normalizeFitData(data).events.length, 0);
});
