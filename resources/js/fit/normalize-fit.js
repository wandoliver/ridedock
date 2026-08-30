const PARSER_NAME = 'fit-file-parser';
const PARSER_VERSION = '5.0.2';

const streamDefinitions = {
    latlng: {
        unit: 'degrees',
        value: (record) => {
            if (!isFiniteNumber(record.position_lat) || !isFiniteNumber(record.position_long)) {
                return null;
            }

            return [record.position_lat, record.position_long];
        },
    },
    heart_rate: { unit: 'bpm', value: (record) => record.heart_rate },
    cadence: { unit: 'rpm', value: (record) => record.cadence },
    power: { unit: 'watts', value: (record) => record.power },
    speed: { unit: 'm/s', value: (record) => record.enhanced_speed ?? record.speed },
    altitude: { unit: 'm', value: (record) => record.enhanced_altitude ?? record.altitude },
};

export function normalizeFitData(fitData) {
    const records = Array.isArray(fitData.records) ? fitData.records : [];
    const session = Array.isArray(fitData.sessions) ? fitData.sessions[0] ?? {} : {};
    const startedAt = firstTimestamp(records, session);

    return {
        parser: {
            name: PARSER_NAME,
            version: PARSER_VERSION,
            protocol_version: fitData.protocolVersion ?? null,
            profile_version: fitData.profileVersion ?? null,
        },
        summary: normalizeSummary(session, records),
        streams: normalizeStreams(records, startedAt),
        events: normalizeShiftEvents(fitData.events, startedAt),
    };
}

function normalizeSummary(session, records) {
    return compactObject({
        distance_m: finiteValue(session.total_distance),
        moving_time_s: finiteValue(session.total_moving_time ?? session.total_timer_time),
        elapsed_time_s: finiteValue(session.total_elapsed_time),
        elevation_gain_m: finiteValue(session.total_ascent),
        average_speed_mps: finiteValue(session.enhanced_avg_speed ?? session.avg_speed),
        max_speed_mps: finiteValue(session.enhanced_max_speed ?? session.max_speed),
        average_heart_rate: finiteValue(session.avg_heart_rate),
        max_heart_rate: finiteValue(session.max_heart_rate),
        average_cadence: finiteValue(session.avg_cadence),
        max_cadence: finiteValue(session.max_cadence),
        average_power: finiteValue(session.avg_power),
        weighted_average_power: finiteValue(session.normalized_power),
        max_power: finiteValue(session.max_power),
        energy_kj: isFiniteNumber(session.total_work) ? session.total_work / 1000 : null,
        started_at: toIsoString(session.start_time ?? records[0]?.timestamp),
    });
}

function normalizeStreams(records, startedAt) {
    const streams = {};

    for (const [type, definition] of Object.entries(streamDefinitions)) {
        const data = records.flatMap((record) => {
            const value = definition.value(record);
            const timestamp = toIsoString(record.timestamp);

            if (value === null || value === undefined || timestamp === null) {
                return [];
            }

            if (!Array.isArray(value) && !isFiniteNumber(value)) {
                return [];
            }

            return [{
                elapsed_seconds: elapsedSeconds(timestamp, startedAt),
                recorded_at: timestamp,
                value,
            }];
        });

        if (data.length > 0) {
            streams[type] = {
                data,
                metadata: {
                    unit: definition.unit,
                    parser: PARSER_NAME,
                },
            };
        }
    }

    return streams;
}

function normalizeShiftEvents(events, startedAt) {
    if (!Array.isArray(events)) {
        return [];
    }

    return events.flatMap((event) => {
        if (!['front_gear_change', 'rear_gear_change'].includes(event.event)) {
            return [];
        }

        const recordedAt = toIsoString(event.timestamp);

        return [{
            type: 'shift',
            elapsed_seconds: elapsedSeconds(recordedAt, startedAt),
            recorded_at: recordedAt,
            data: compactObject({
                direction: event.event === 'front_gear_change' ? 'front' : 'rear',
                event_type: event.event_type ?? null,
                front_gear_number: finiteValue(event.front_gear_num),
                front_gear_teeth: finiteValue(event.front_gear),
                rear_gear_number: finiteValue(event.rear_gear_num),
                rear_gear_teeth: finiteValue(event.rear_gear),
            }),
        }];
    });
}

function firstTimestamp(records, session) {
    return toIsoString(records[0]?.timestamp ?? session.start_time ?? session.timestamp);
}

function elapsedSeconds(timestamp, startedAt) {
    if (timestamp === null || startedAt === null) {
        return null;
    }

    return Math.max(0, Math.round((Date.parse(timestamp) - Date.parse(startedAt)) / 1000));
}

function toIsoString(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);

    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}

function finiteValue(value) {
    return isFiniteNumber(value) ? value : null;
}

function isFiniteNumber(value) {
    return typeof value === 'number' && Number.isFinite(value);
}

function compactObject(value) {
    return Object.fromEntries(Object.entries(value).filter(([, item]) => item !== null && item !== undefined));
}
