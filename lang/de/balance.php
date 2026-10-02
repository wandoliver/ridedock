<?php

return [
    'title' => 'Belastungsmonotonie und Balance',
    'period' => 'Abgeschlossene Kalendertage bis :date; der heutige Tag zählt noch nicht.',
    'monotony' => 'Monotonie · 7 Tage', 'load_balance' => 'Belastungsbalance · 42 / 7 Tage',
    'monotony_hint' => 'Mittlere Tagesbelastung / Standardabweichung der sieben Tageswerte.',
    'load_balance_hint' => '42-Tage-Mittel minus 7-Tage-Mittel, in RPE-Minuten pro Tag. Positiv: die letzte Woche liegt unter dem längeren Mittel.',
    'strain' => 'Load × Monotonie', 'details' => 'Tageswerte, Datenbasis und Rechenweg',
    'coverage' => ':days Tage: :rated von :total Fahrten mit Dauer und RPE; :missing unbekannte Belastungen.',
    'formula' => 'Tagesbelastung = Summe aus Fahrzeit in Minuten × RPE. Monotonie nutzt die Populations-Standardabweichung einschließlich Tagen ohne aufgezeichnete Fahrt. Balance vergleicht einfache Tagesmittel; keine exponentielle Fitnessmodellierung.',
    'insufficient_history' => 'Die Aufzeichnung reicht noch nicht bis zum Beginn des benötigten Zeitraums.',
    'incomplete_load' => 'Mindestens einer Fahrt fehlen Dauer oder gültige RPE; die Belastung bleibt unbekannt.',
    'no_load' => 'Im benötigten Zeitraum ist keine positive Belastung aufgezeichnet.',
    'no_variation' => 'Identische Tagesbelastungen: Standardabweichung null, Monotonie nicht definiert.',
    'recording_limit' => 'Tage ohne aufgezeichnete Fahrt zählen innerhalb der vorhandenen Historie als 0. Fehlende Importe oder andere Sportarten werden nicht erkannt; die Werte beschreiben nur deine erfassten Radfahrten.',
    'freshness_limit' => 'Keine Frische- oder Erholungsbewertung: Dafür fehlen direkte Erholungsdaten. Monotonie und Balance sagen weder Trainingsbereitschaft noch Verletzungsrisiko voraus.',
];
