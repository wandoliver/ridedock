<?php

return [
    'title' => 'Gear shifting',
    'description' => 'Recorded gear changes, positions and tooth counts from your FIT file.',
    'front' => 'Front', 'rear' => 'Rear', 'unknown' => 'Unknown', 'total' => 'Gear changes',
    'time' => 'Ride time', 'component' => 'Changed', 'front_gear' => 'Chainring', 'rear_gear' => 'Sprocket', 'ratio' => 'Gear ratio',
    'number' => 'Gear :number', 'teeth' => ':number teeth',
    'details' => 'Gear change timeline',
    'empty' => 'No usable gear shifting data recorded. Gear changes cannot be reconstructed from power or cadence without this recording.',
    'limits' => 'Values show the reported gear after each change. Missing values remain unknown. Time spent in each gear is not estimated; the recording may be incomplete.',
    'ignored' => ':count incomplete or ambiguous gear-change messages were omitted.',
    'truncated' => 'The timeline shows the first 200 recorded gear changes. Counters include all of them.',
    'reprocess' => 'Reprocess FIT file',
    'reprocess_hint' => 'Reprocess the stored original file with the current parser. Measurements and gear shifting are updated; your notes and RPE rating are preserved.',
    'no_file' => 'No usable original file is stored for this ride.',
    'failed' => 'The original file could not be processed. The previous analysis is preserved.',
    'updated' => 'Original file reprocessed. Measurements and gear shifting are updated.',
];
