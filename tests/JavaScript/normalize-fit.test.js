import assert from 'node:assert/strict';
import test from 'node:test';
import FitParser, { FitBaseType, FitEncoder } from 'fit-file-parser';
import { normalizeFitData } from '../../resources/js/fit/normalize-fit.js';

test('normalizes summaries, streams and standard FIT shift events', () => {
    const result = normalizeFitData({
        protocolVersion: 32,
        profileVersion: 21188,
        sessions: [{
            start_time: new Date('2026-08-30T07:00:00Z'),
            total_distance: 42500.5,
            total_timer_time: 5400,
            total_elapsed_time: 5500,
            total_ascent: 620,
            enhanced_avg_speed: 7.87,
            avg_heart_rate: 142,
            normalized_power: 233,
            total_work: 980000,
        }],
        records: [
            {
                timestamp: new Date('2026-08-30T07:00:00Z'),
                position_lat: 0.01,
                position_long: 0.02,
                heart_rate: 130,
                cadence: 82,
                power: 210,
                enhanced_speed: 8.2,
                enhanced_altitude: 290,
            },
            {
                timestamp: new Date('2026-08-30T07:00:05Z'),
                position_lat: 0.011,
                position_long: 0.021,
                heart_rate: 136,
                power: 250,
            },
        ],
        events: [{
            timestamp: new Date('2026-08-30T07:00:05Z'),
            event: 'rear_gear_change',
            event_type: 'marker',
            front_gear_num: 2,
            front_gear: 48,
            rear_gear_num: 7,
            rear_gear: 21,
        }],
    });

    assert.equal(result.parser.version, '5.0.2');
    assert.equal(result.summary.distance_m, 42500.5);
    assert.equal(result.summary.energy_kj, 980);
    assert.deepEqual(result.streams.latlng.data[1], {
        elapsed_seconds: 5,
        recorded_at: '2026-08-30T07:00:05.000Z',
        value: [0.011, 0.021],
    });
    assert.equal(result.streams.cadence.data.length, 1);
    assert.deepEqual(result.events[0].data, {
        direction: 'rear',
        event_type: 'marker',
        front_gear_number: 2,
        front_gear_teeth: 48,
        rear_gear_number: 7,
        rear_gear_teeth: 21,
    });
});

test('omits unavailable values instead of inventing zeroes', () => {
    const result = normalizeFitData({
        sessions: [{}],
        records: [{ timestamp: new Date('2026-08-30T07:00:00Z'), power: 0 }],
        events: [],
    });

    assert.deepEqual(result.summary, { started_at: '2026-08-30T07:00:00.000Z' });
    assert.equal(result.streams.power.data[0].value, 0);
    assert.equal(result.streams.latlng, undefined);
});

test('parses a strict CRC-valid FIT file before normalization', async () => {
    const startedAt = new Date('2026-08-30T07:00:00Z');
    const latitude = 0.01;
    const longitude = 0.02;
    const degreesToSemicircles = (degrees) => Math.round(degrees * (2 ** 31) / 180);
    const encoder = new FitEncoder();

    encoder.writeMessage(18, [
        { number: 2, size: 4, baseType: FitBaseType.Uint32, value: FitEncoder.toFitTimestamp(startedAt) },
        { number: 7, size: 4, baseType: FitBaseType.Uint32, value: 5_400_000 },
        { number: 8, size: 4, baseType: FitBaseType.Uint32, value: 5_350_000 },
        { number: 9, size: 4, baseType: FitBaseType.Uint32, value: 4_250_050 },
        { number: 20, size: 2, baseType: FitBaseType.Uint16, value: 225 },
    ]);
    encoder.writeMessage(20, [
        { number: 253, size: 4, baseType: FitBaseType.Uint32, value: FitEncoder.toFitTimestamp(startedAt) },
        { number: 0, size: 4, baseType: FitBaseType.Sint32, value: degreesToSemicircles(latitude) },
        { number: 1, size: 4, baseType: FitBaseType.Sint32, value: degreesToSemicircles(longitude) },
        { number: 2, size: 2, baseType: FitBaseType.Uint16, value: 3_950 },
        { number: 3, size: 1, baseType: FitBaseType.Uint8, value: 132 },
        { number: 6, size: 2, baseType: FitBaseType.Uint16, value: 8_200 },
        { number: 7, size: 2, baseType: FitBaseType.Uint16, value: 210 },
    ]);

    const parsed = await new FitParser({ force: false, mode: 'list', speedUnit: 'm/s', lengthUnit: 'm' })
        .parseAsync(Buffer.from(encoder.close()));
    const result = normalizeFitData(parsed);

    assert.equal(result.summary.distance_m, 42_500.5);
    assert.equal(result.summary.average_power, 225);
    assert.ok(Math.abs(result.streams.latlng.data[0].value[0] - latitude) < 0.000001);
    assert.ok(Math.abs(result.streams.latlng.data[0].value[1] - longitude) < 0.000001);
    assert.equal(result.streams.altitude.data[0].value, 290);
    assert.equal(result.streams.speed.data[0].value, 8.2);
});
