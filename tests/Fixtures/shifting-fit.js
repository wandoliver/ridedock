import { pathToFileURL } from 'node:url';
import { FitBaseType, FitEncoder } from 'fit-file-parser';

// Invented timestamps, gears, device identity and developer application ID.
// This is a protocol fixture, never a rider recording or a claimed SRAM app schema.
export function shiftingFit({ developerFields = false } = {}) {
    const start = new Date('2026-08-30T07:00:00Z');
    const encoder = new FitEncoder({ protocolVersion: 32 });
    encoder.writeMessage(18, [
        field(2, 4, FitBaseType.Uint32, FitEncoder.toFitTimestamp(start)),
        field(7, 4, FitBaseType.Uint32, 120_000),
        field(8, 4, FitBaseType.Uint32, 120_000),
        field(9, 4, FitBaseType.Uint32, 100_000),
    ]);
    encoder.writeMessage(23, [field(0, 1, FitBaseType.Uint8, 0), field(2, 2, FitBaseType.Uint16, 268)]);
    encoder.writeMessage(20, [field(253, 4, FitBaseType.Uint32, FitEncoder.toFitTimestamp(start)), field(7, 2, FitBaseType.Uint16, 180)]);
    encoder.writeMessage(20, [field(253, 4, FitBaseType.Uint32, FitEncoder.toFitTimestamp(start) + 120), field(7, 2, FitBaseType.Uint16, 220)]);
    const shifts = [
        { seconds: 10, event: 43, rear: 7, rearTeeth: 21, front: 2, frontTeeth: 48 },
        { seconds: 30, event: 43, rear: 8, rearTeeth: 19, front: 2, frontTeeth: 48 },
        { seconds: 40, event: 42, rear: 8, rearTeeth: 19, front: 1, frontTeeth: 35 },
        { seconds: 60, event: 43, rear: 9, rearTeeth: 17, front: 1, frontTeeth: 35 },
    ];
    if (!developerFields) {
        for (const shift of shifts) {
            encoder.writeMessage(21, [
                field(253, 4, FitBaseType.Uint32, FitEncoder.toFitTimestamp(start) + shift.seconds),
                field(0, 1, FitBaseType.Enum, shift.event), field(1, 1, FitBaseType.Enum, 3),
                field(3, 4, FitBaseType.Uint32, packGears(shift)),
            ]);
        }
        return Buffer.from(encoder.close());
    }
    encoder.writeMessage(207, [
        field(1, 16, FitBaseType.Byte, Uint8Array.from({ length: 16 }, (_, index) => index + 1)),
        field(2, 2, FitBaseType.Uint16, 268), field(3, 1, FitBaseType.Uint8, 0),
    ]);
    const name = FitEncoder.string('synthetic_shift_state');
    encoder.writeMessage(206, [
        field(0, 1, FitBaseType.Uint8, 0), field(1, 1, FitBaseType.Uint8, 0),
        field(2, 1, FitBaseType.Uint8, FitBaseType.Uint32), field(3, name.length, FitBaseType.String, name),
        field(14, 2, FitBaseType.Uint16, 21), field(15, 1, FitBaseType.Uint8, 3),
    ]);
    // FitEncoder has no developer-field API. Append a minimal FIT v2 definition
    // (local message 15, event 21, three native fields, one developer field).
    const extra = [0x6f, 0, 0, 21, 0, 3,
        253, 4, FitBaseType.Uint32, 0, 1, FitBaseType.Enum, 1, 1, FitBaseType.Enum,
        1, 0, 4, 0];
    for (const shift of shifts) {
        const values = Buffer.alloc(11);
        values[0] = 15;
        values.writeUInt32LE(FitEncoder.toFitTimestamp(start) + shift.seconds, 1);
        values[5] = shift.event;
        values[6] = 3;
        values.writeUInt32LE(packGears(shift), 7);
        extra.push(...values);
    }
    const original = Buffer.from(encoder.close());
    const headerSize = original[0];
    const body = Buffer.concat([original.subarray(headerSize, -2), Buffer.from(extra)]);
    const header = Buffer.from(original.subarray(0, headerSize));
    header.writeUInt32LE(body.length, 4);
    header.writeUInt16LE(FitEncoder.calculateCRC(header.subarray(0, 12)), 12);
    const file = Buffer.concat([header, body, Buffer.alloc(2)]);
    file.writeUInt16LE(FitEncoder.calculateCRC(file.subarray(0, -2)), file.length - 2);
    return file;
}

export function packGears({ rear, rearTeeth, front, frontTeeth }) {
    return (rear + rearTeeth * 256 + front * 65_536 + frontTeeth * 16_777_216) >>> 0;
}

function field(number, size, baseType, value) {
    return { number, size, baseType, value };
}

if (process.argv[1] && pathToFileURL(process.argv[1]).href === import.meta.url) {
    process.stdout.write(shiftingFit({ developerFields: process.argv.includes('--developer-fields') }));
}
