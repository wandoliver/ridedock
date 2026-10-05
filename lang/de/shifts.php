<?php

return [
    'title' => 'Schaltinformationen',
    'description' => 'Aufgezeichnete Gangwechsel, Gangpositionen und Zähnezahlen aus deiner FIT-Datei.',
    'front' => 'Vorne', 'rear' => 'Hinten', 'unknown' => 'Unbestimmt', 'total' => 'Schaltvorgänge',
    'time' => 'Fahrtzeit', 'component' => 'Geschaltet', 'front_gear' => 'Kettenblatt', 'rear_gear' => 'Ritzel', 'ratio' => 'Übersetzung',
    'number' => 'Gang :number', 'teeth' => ':number Zähne',
    'details' => 'Gangwechsel im Verlauf',
    'empty' => 'Keine auswertbaren Schaltinformationen aufgezeichnet. Ohne diese Aufzeichnung lassen sich Gangwechsel nicht nachträglich aus Leistung oder Trittfrequenz ableiten.',
    'limits' => 'Die Werte zeigen den gemeldeten Gang nach dem Schaltvorgang. Fehlende Angaben bleiben offen. Daraus wird keine Nutzungsdauer je Gang geschätzt; die Aufzeichnung kann unvollständig sein.',
    'ignored' => ':count unvollständige oder nicht eindeutig zuordenbare Schaltmeldungen wurden ausgelassen.',
    'truncated' => 'Der Verlauf zeigt die ersten 200 aufgezeichneten Gangwechsel. Die Zähler berücksichtigen alle.',
    'reprocess' => 'FIT-Datei erneut auswerten',
    'reprocess_hint' => 'Werte die gespeicherte Originaldatei mit dem aktuellen Parser erneut aus. Messwerte und Schaltinformationen werden aktualisiert; deine Notizen und RPE-Bewertung bleiben erhalten.',
    'no_file' => 'Für diese Fahrt ist keine auswertbare Originaldatei mehr gespeichert.',
    'failed' => 'Die Originaldatei konnte nicht ausgewertet werden. Die bisherige Auswertung bleibt erhalten.',
    'updated' => 'Originaldatei erneut ausgewertet. Messwerte und Schaltinformationen sind aktualisiert.',
];
