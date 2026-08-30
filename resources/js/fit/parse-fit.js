import { readFile, stat } from 'node:fs/promises';
import FitParser from 'fit-file-parser';
import { normalizeFitData } from './normalize-fit.js';

const filePath = process.argv[2];
const maximumBytes = Number.parseInt(process.argv[3] ?? '52428800', 10);

if (!filePath) {
    fail('A FIT file path is required.');
}

try {
    const file = await stat(filePath);

    if (!file.isFile()) {
        fail('The FIT source is not a regular file.');
    }

    if (file.size === 0 || file.size > maximumBytes) {
        fail('The FIT source has an invalid file size.');
    }

    const parser = new FitParser({
        force: false,
        mode: 'list',
        speedUnit: 'm/s',
        lengthUnit: 'm',
    });
    const parsed = await parser.parseAsync(await readFile(filePath));

    process.stdout.write(JSON.stringify(normalizeFitData(parsed)));
} catch (error) {
    fail(error instanceof Error ? error.message : String(error));
}

function fail(message) {
    process.stderr.write(`${message}\n`);
    process.exit(1);
}
