<?php

return [
    'sensor_title' => 'Time in sensor zones',
    'sensor_hint' => 'Recorded time classified using the reference effective for each ride. Percentages use classified sensor time; recorded pauses may be included. Gaps over 30 seconds and trailing samples without a successor are not extrapolated.',
    'reference_missing' => 'No dated reference is available for this ride.',
    'samples_missing' => 'No classifiable sensor measurements available.',
    'sensor_coverage' => ':percent% of the timeline classified · :minutes min unknown',
    'title' => 'Zones & measurement history',
    'hint' => 'Power: six continuous FTP bands at 55, 75, 90, 105 and 120%. Heart rate: five HRmax bands from 50 to 100%, plus below/above range. These bands classify recorded measurements.',
    'power' => 'Power bands', 'heart_rate' => 'Heart-rate bands',
    'missing' => 'No dated reference value yet.',
    'add' => 'Add a dated measurement', 'effective' => 'Effective from',
    'dated_hint' => 'Each entry contains both references; blank fields mean unknown. An intentionally backdated entry applies from that instant until the next entry. Normal profile edits apply when saved. Existing AI outputs are retained.',
    'save' => 'Save measurements', 'saved' => 'Dated measurements saved.',
    'history' => 'Last 20 entries', 'no_history' => 'No dated measurements yet.',
];
