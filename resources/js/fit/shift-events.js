// FIT Profile event (21): data (3), gear_change_data components in low-byte order:
// rear gear number (11), rear teeth (12), front gear number (9), front teeth (10).
const gearFields = {
    9: ['front_gear_num', 'front_gear_number'],
    10: ['front_gear', 'front_gear_teeth'],
    11: ['rear_gear_num', 'rear_gear_number'],
    12: ['rear_gear', 'rear_gear_teeth'],
};
const nativeNames = new Set(['timestamp', 'event', 'event_type', 'data', 'gear_change_data', ...Object.values(gearFields).map(([name]) => name)]);

export function normalizeShiftEvents(fitData, startedAt) {
    const events = Array.isArray(fitData.events) ? fitData.events : [];
    const descriptions = Array.isArray(fitData.field_descriptions) ? fitData.field_descriptions : [];
    const identities = Array.isArray(fitData.developer_data_ids) ? fitData.developer_data_ids : [];
    const fields = developerMappings(descriptions, identities);
    // The upstream parser flattens developer values into message properties.
    // Native-name collisions cannot be disambiguated safely after that step.
    const collision = descriptions.some((description) => nativeNames.has(description.field_name));
    const normalized = [];
    const seen = new Set();
    let ignored = 0;

    for (const event of events) {
        const direction = ['front_gear_change', 42].includes(event?.event) ? 'front'
            : ['rear_gear_change', 43].includes(event?.event) ? 'rear' : null;
        if (direction === null) continue;
        const recordedAt = eventTimestamp(event.timestamp);
        if (collision || recordedAt === null || (startedAt !== null && Date.parse(recordedAt) < Date.parse(startedAt))) {
            ignored++;
            continue;
        }
        const developerValue = (number) => {
            const mapping = fields.get(number);
            return mapping && Object.hasOwn(event, mapping) ? event[mapping] : undefined;
        };
        const packed = uint32(event.data) ?? uint32(event.gear_change_data) ?? uint32(developerValue(3));
        const components = packed === null ? {} : {
            11: packed & 0xff, 12: (packed >>> 8) & 0xff,
            9: (packed >>> 16) & 0xff, 10: (packed >>> 24) & 0xff,
        };
        const data = { direction };
        if (typeof event.event_type === 'string' && /^[a-z_]{1,40}$/.test(event.event_type)) data.event_type = event.event_type;
        for (const [number, [nativeName, outputName]] of Object.entries(gearFields)) {
            const value = gearValue(event[nativeName]) ?? gearValue(components[number]) ?? gearValue(developerValue(Number(number)));
            if (value !== null) data[outputName] = value;
        }
        if (!data[`${direction}_gear_number`] && !data[`${direction}_gear_teeth`]) {
            ignored++;
            continue;
        }
        const key = JSON.stringify([recordedAt, direction, ...Object.values(gearFields).map(([, outputName]) => data[outputName] ?? null)]);
        if (seen.has(key)) continue;
        seen.add(key);
        normalized.push({
            type: 'shift',
            elapsed_seconds: startedAt === null ? null : Math.round((Date.parse(recordedAt) - Date.parse(startedAt)) / 1000),
            recorded_at: recordedAt,
            data,
        });
    }
    normalized.sort((a, b) => Date.parse(a.recorded_at) - Date.parse(b.recorded_at));
    return { events: normalized, ignored };
}

function developerMappings(descriptions, identities) {
    const mappings = new Map();
    const names = new Map();
    const targets = new Map();
    for (const description of descriptions) {
        const name = description.field_name;
        names.set(name, (names.get(name) ?? 0) + 1);
        if (!['event', 21].includes(description.native_mesg_num)) continue;
        const number = description.native_field_num;
        if (number !== 3 && !Object.hasOwn(gearFields, number)) continue;
        targets.set(number, (targets.get(number) ?? 0) + 1);
        if (typeof name !== 'string' || name === '' || nativeNames.has(name)) continue;
        if (!identities.some((identity) => identity.developer_data_index === description.developer_data_index)) continue;
        // The parser applies scale/offset; packed bits and gear indices require identity scaling.
        if ((description.scale ?? 1) !== 1 || (description.offset ?? 0) !== 0 || (description.array ?? 0) > 1) continue;
        const type = description.fit_base_type_id;
        const allowed = number === 3 ? ['uint32', 134] : ['uint8z', 'uint8', 10, 2];
        if (!allowed.includes(type)) continue;
        mappings.set(number, name);
    }
    for (const [number, name] of mappings) {
        if (names.get(name) !== 1 || targets.get(number) !== 1) mappings.delete(number);
    }
    return mappings;
}

function gearValue(value) {
    // FIT uint8z components use zero as missing; don't invent a 1x chainring.
    return Number.isInteger(value) && value >= 1 && value <= 255 ? value : null;
}

function uint32(value) {
    return Number.isInteger(value) && value >= 0 && value < 0xffffffff ? value : null;
}

function eventTimestamp(value) {
    if (!(value instanceof Date) && (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T/.test(value))) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date.toISOString();
}
