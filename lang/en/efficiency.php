<?php

return [
    'title' => 'Comparable efficiency', 'trends' => 'Efficiency over time · 6 weeks',
    'hint' => 'Watts per heart-rate beat from a steady segment with both sensors.',
    'trends_hint' => 'Same comparison group and ride type; segment duration ±20%, mean power ±10%. Compared with the latest matching ride in the preceding six weeks.',
    'window' => 'From minute :start · :minutes min · :watts W · :bpm bpm',
    'no_comparison' => 'No matching comparison ride yet.',
    'change' => ':percent% compared with :date',
    'reference_missing' => 'No historical FTP reference for this ride.',
    'samples_missing' => 'No overlapping power and heart-rate measurements.',
    'steady_window_missing' => 'No continuous segment of at least 20 minutes after the first 10 minutes: 55–75% FTP, power variation at most 10%.',
    'group' => 'Comparison group', 'group_example' => 'e.g. Indoor endurance',
    'group_hint' => 'Assign similar conditions yourself. Leave blank to remove the group.',
    'save' => 'Save group', 'saved' => 'Comparison group saved.',
    'date' => 'Ride', 'previous' => 'Previous matching ride',
    'empty' => 'No grouped rides with an eligible measurement segment yet. Assign comparison groups on activity pages.',
    'limitation' => 'Descriptive mean W/bpm. Temperature, fatigue and measurement conditions can change it; not a fitness or recovery score.',
];
