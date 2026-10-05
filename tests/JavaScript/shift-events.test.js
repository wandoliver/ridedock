import assert from 'node:assert/strict';
import test from 'node:test';
import FitParser from 'fit-file-parser';
import { normalizeFitData } from '../../resources/js/fit/normalize-fit.js';
import { packGears, shiftingFit } from '../Fixtures/shifting-fit.js';

const timestamp = '2026-08-30T07:00:10Z';
const start = '2026-08-30T07:00:00Z';
const gear = { rear: 7, rearTeeth: 21, front: 2, frontTeeth: 48 };
const packed = packGears(gear);
const event = { timestamp, event: 'rear_gear_change', event_type: 'marker', data: packed };
const result = (events, extra = {}) => normalizeFitData({ sessions: [{ start_time: start }], events, ...extra });

for (const developerFields of [false, true]) {
    test(`decodes a CRC-valid synthetic FIT with ${developerFields ? 'described developer' : 'native packed'} gears`, async () => {
        const parsed = await new FitParser({ force: false, mode: 'list', speedUnit: 'm/s', lengthUnit: 'm' }).parseAsync(shiftingFit({ developerFields }));
        const normalized = normalizeFitData(parsed);
        assert.equal(normalized.events.length, 4);
        assert.equal(normalized.parser.shift_events_ignored, 0);
        assert.deepEqual(normalized.events[0], {
            type: 'shift', elapsed_seconds: 10, recorded_at: '2026-08-30T07:00:10.000Z',
            data: { direction: 'rear', event_type: 'marker', front_gear_number: 2, front_gear_teeth: 48, rear_gear_number: 7, rear_gear_teeth: 21 },
        });
        assert.equal(normalized.events[2].data.direction, 'front');
        assert.equal(normalized.events[2].data.front_gear_teeth, 35);
    });
}

test('keeps unknown 1x front values missing and ignores malformed or unrelated events', () => {
    const oneBy = { ...event, data: packGears({ rear: 12, rearTeeth: 44, front: 0, frontTeeth: 0 }) };
    const normalized = result([oneBy, { ...event, event: 'battery' }, ...[undefined, 0, 0xffffffff, -1, 2 ** 32, NaN, 'packed', []].map((data) => ({ ...event, data })),
        { ...event, timestamp: null }, { ...event, timestamp: 'invalid' }, { ...event, timestamp: false }, { ...event, timestamp: '2026-08-29T07:00:00Z' }]);
    assert.equal(normalized.events.length, 1);
    assert.equal(normalized.events[0].data.front_gear_number, undefined);
    assert.equal(normalized.events[0].data.front_gear_teeth, undefined);
    assert.equal(normalized.events[0].data.rear_gear_teeth, 44);
    assert.equal(normalized.parser.shift_events_ignored, 12);
});

test('explicit components take precedence and repeated events are deduplicated and sorted', () => {
    const later = { ...event, timestamp: '2026-08-30T07:00:30Z', rear_gear_num: 8, rear_gear: 19 };
    const normalized = result([later, event, event, { ...event, event_type: 'start' }, { ...event, event: 42 }]);
    assert.equal(normalized.events.length, 3);
    assert.equal(normalized.events[0].elapsed_seconds, 10);
    assert.equal(normalized.events.at(-1).data.rear_gear_number, 8);
    assert.equal(normalized.events.at(-1).data.rear_gear_teeth, 19);
});

test('developer values require explicit native metadata and unambiguous field identities', () => {
    const description = { developer_data_index: 0, field_definition_number: 0, fit_base_type_id: 'uint32', field_name: 'synthetic_state', native_mesg_num: 'event', native_field_num: 3 };
    const extra = { developer_data_ids: [{ developer_data_index: 0 }], field_descriptions: [description] };
    const developerEvent = { timestamp, event: 'rear_gear_change', synthetic_state: packed };
    assert.equal(result([developerEvent], extra).events.length, 1);
    for (const field_descriptions of [[], [{ ...description, native_mesg_num: 'record' }], [{ ...description, native_field_num: undefined }],
        [{ ...description, scale: 2 }], [{ ...description, fit_base_type_id: 'float32' }], [description, { ...description, developer_data_index: 1 }],
        [description, { ...description, field_name: 'other_state' }]]) {
        assert.equal(result([developerEvent], { ...extra, field_descriptions }).events.length, 0);
    }
    assert.equal(result([developerEvent], { ...extra, developer_data_ids: [] }).events.length, 0);
    assert.equal(result([event], { ...extra, field_descriptions: [{ ...description, field_name: 'data' }] }).events.length, 0);
});

test('ordinary record developer values cannot invent shift events', () => {
    assert.equal(normalizeFitData({ records: [{ timestamp, axs_rear_gear: 7, axs_front_gear: 2 }] }).events.length, 0);
});

test('decodes individually described gear fields without assuming a manufacturer schema', () => {
    const field_descriptions = [9, 10, 11, 12].map((number) => ({
        developer_data_index: 0, field_definition_number: number, fit_base_type_id: 'uint8z',
        field_name: `synthetic_${number}`, native_mesg_num: 21, native_field_num: number,
    }));
    const normalized = result([{ timestamp, event: 43, synthetic_9: 2, synthetic_10: 48, synthetic_11: 7, synthetic_12: 21 }], {
        field_descriptions, developer_data_ids: [{ developer_data_index: 0 }],
    });
    assert.deepEqual(normalized.events[0].data, {
        direction: 'rear', front_gear_number: 2, front_gear_teeth: 48, rear_gear_number: 7, rear_gear_teeth: 21,
    });
});

test('uses one start time for summary and shifting, and preserves shifts without a ride start', () => {
    const normalized = normalizeFitData({ records: [{ timestamp }, { timestamp: start }], events: [event] });
    assert.equal(normalized.summary.started_at, '2026-08-30T07:00:00.000Z');
    assert.equal(normalized.events[0].elapsed_seconds, 10);
    const withoutStart = normalizeFitData({ events: [event] });
    assert.equal(withoutStart.events[0].elapsed_seconds, null);
    assert.equal(withoutStart.summary.started_at, undefined);
});

test('treats high packed bytes as unsigned values', () => {
    const normalized = result([{ ...event, data: packGears({ rear: 1, rearTeeth: 10, front: 1, frontTeeth: 128 }) }]);
    assert.equal(normalized.events[0].data.front_gear_teeth, 128);
    assert.equal(normalized.events[0].data.rear_gear_teeth, 10);
});
