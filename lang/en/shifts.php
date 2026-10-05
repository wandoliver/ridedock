<?php

return [
    'title' => 'Gear shifting',
    'description' => 'Recorded gear changes, positions and tooth counts from your FIT file.',
    'front' => 'Front', 'rear' => 'Rear', 'unknown' => 'Unknown', 'total' => 'Gear changes',
    'time' => 'Ride time', 'component' => 'Changed', 'front_gear' => 'Chainring', 'rear_gear' => 'Sprocket', 'ratio' => 'Gear ratio',
    'number' => 'Gear :number', 'teeth' => ':number teeth',
    'details' => 'Gear change timeline',
    'status_not_evaluated' => 'The previous analysis has no gear shifting data and predates the current gearing diagnostics. Reprocess the stored FIT file if it is still available.',
    'status_absent' => 'No recognizable gear values or shift messages were found in the stored FIT file. Gear changes cannot be reconstructed from power or cadence without this data.',
    'status_described_only' => 'The FIT file describes gear fields but has no recognizable recorded gear values or shift messages. Field descriptions alone are not a gear shifting recording.',
    'status_unsupported' => 'The FIT file contains gear values but no usable shift messages. This recording format is not yet evaluated as gear changes.',
    'status_incomplete' => 'The FIT file contains shift messages with missing or ambiguous gear values or timestamps.',
    'recording_hint' => 'Check that the electronic drivetrain is connected as a sensor on your bike computer and displays gearing during the ride. Having SRAM on the bike alone does not confirm recording in this file.',
    'limits' => 'Values show the reported gear after each change. Missing values remain unknown. Time spent in each gear is not estimated; the recording may be incomplete.',
    'ignored' => ':count incomplete or ambiguous gear-change messages were omitted.',
    'truncated' => 'The timeline shows the first 200 recorded gear changes. Counters include all of them.',
    'reprocess' => 'Reprocess FIT file',
    'reprocess_hint' => 'Reprocess the stored original file with the current parser. Measurements and gear shifting are updated; your notes and RPE rating are preserved.',
    'no_file' => 'No usable original file is stored for this ride.',
    'failed' => 'The original file could not be processed. The previous analysis is preserved.',
    'updated' => 'Original file reprocessed. Measurements and gear shifting are updated.',
];
