<?php

return [
    'title' => 'Vergleichbare Effizienz', 'trends' => 'Effizienz im Verlauf · 6 Wochen',
    'hint' => 'Watt pro Pulsschlag aus einem gleichmäßigen Abschnitt mit beiden Sensoren.',
    'trends_hint' => 'Gleiche Vergleichsgruppe und Fahrtart; Abschnittsdauer ±20 %, mittlere Leistung ±10 %. Vergleich zur letzten passenden Fahrt in den vorherigen sechs Wochen.',
    'window' => 'Ab Minute :start · :minutes min · :watts W · :bpm bpm',
    'no_comparison' => 'Noch keine passende Vergleichsfahrt.',
    'change' => ':percent % gegenüber :date',
    'reference_missing' => 'Keine historische FTP-Referenz für diese Fahrt.',
    'samples_missing' => 'Keine gemeinsamen Leistungs- und Pulsmessungen.',
    'steady_window_missing' => 'Kein durchgängiger Abschnitt von mindestens 20 Minuten nach den ersten 10 Minuten: 55–75 % FTP, Leistungsschwankung höchstens 10 %.',
    'group' => 'Vergleichsgruppe', 'group_example' => 'z. B. Indoor-Ausdauer',
    'group_hint' => 'Gleichartige Bedingungen selbst zuordnen. Leeren entfernt die Zuordnung.',
    'save' => 'Gruppe speichern', 'saved' => 'Vergleichsgruppe gespeichert.',
    'date' => 'Fahrt', 'previous' => 'Vorherige passende Fahrt',
    'empty' => 'Noch keine zugeordneten Fahrten mit einem geeigneten Messabschnitt. Vergleichsgruppen kannst du auf den Fahrtseiten vergeben.',
    'limitation' => 'Beschreibender Mittelwert W/bpm. Temperatur, Müdigkeit und Messbedingungen können ihn verändern; kein Fitness- oder Erholungsscore.',
];
