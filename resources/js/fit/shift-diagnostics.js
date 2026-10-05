// Descriptions declare possible fields; only message values establish recorded data.
const gearNames = new Set([
    'front_gear', 'rear_gear', 'front_gear_num', 'rear_gear_num',
    'front_gear_number', 'rear_gear_number', 'front_gear_teeth', 'rear_gear_teeth',
]);

export function shiftDiagnostics(fitData, shifts) {
    const descriptions = Array.isArray(fitData.field_descriptions) ? fitData.field_descriptions : [];
    const names = new Set(gearNames);
    let described = false;
    for (const description of descriptions) {
        if (!description || typeof description.field_name !== 'string') continue;
        const equivalent = ['event', 21].includes(description.native_mesg_num)
            && [3, 9, 10, 11, 12].includes(description.native_field_num);
        if (gearNames.has(description.field_name) || equivalent) {
            described = true;
            names.add(description.field_name);
        }
    }
    const events = Array.isArray(fitData.events) ? fitData.events : [];
    const shiftMessages = events.filter((event) => ['front_gear_change', 'rear_gear_change', 42, 43].includes(event?.event)).length;
    // `messages` includes other FIT message kinds; the convenience arrays do not.
    const groups = fitData.messages && typeof fitData.messages === 'object' ? Object.values(fitData.messages)
        : [fitData.records, fitData.events];
    const recordedNames = [...names];
    let gearMessages = 0;
    for (const group of groups) {
        if (!Array.isArray(group)) continue;
        for (const message of group) {
            if (!message || typeof message !== 'object') continue;
            if (recordedNames.some((name) => Number.isInteger(message[name]) && message[name] > 0 && message[name] < 0xffffffff)) gearMessages++;
        }
    }
    const status = shifts.events.length > 0 ? 'available' : shiftMessages > 0 ? 'incomplete'
        : gearMessages > 0 ? 'unsupported' : described ? 'described_only' : 'absent';
    return { status, shift_messages: shiftMessages, gear_value_messages: gearMessages, fields_described: described };
}
