<?php

return [
    'title' => 'Schaltinformationen',
    'description' => 'Aufgezeichnete Gangwechsel, Gangpositionen und Zähnezahlen aus deiner FIT-Datei.',
    'front' => 'Vorne', 'rear' => 'Hinten', 'unknown' => 'Unbestimmt', 'total' => 'Schaltvorgänge',
    'time' => 'Fahrtzeit', 'component' => 'Geschaltet', 'front_gear' => 'Kettenblatt', 'rear_gear' => 'Ritzel', 'ratio' => 'Übersetzung',
    'number' => 'Gang :number', 'teeth' => ':number Zähne',
    'details' => 'Gangwechsel im Verlauf',
    'status_not_evaluated' => 'Die bisherige Auswertung enthält keine Schaltinformationen. Sie stammt noch nicht aus der aktuellen Prüfung der Gangdaten. Werte die gespeicherte FIT-Datei erneut aus, falls sie noch vorhanden ist.',
    'status_absent' => 'In der gespeicherten FIT-Datei wurden keine erkennbaren Gangwerte oder Schaltmeldungen gefunden. Ohne diese Daten lassen sich Gangwechsel nicht nachträglich aus Leistung oder Trittfrequenz ableiten.',
    'status_described_only' => 'Die FIT-Datei beschreibt Gangfelder, enthält aber keine erkennbaren aufgezeichneten Gangwerte oder Schaltmeldungen. Feldbeschreibungen allein sind noch keine Schaltaufzeichnung.',
    'status_unsupported' => 'Die FIT-Datei enthält Gangwerte, aber keine auswertbaren Schaltmeldungen. Dieses Aufzeichnungsformat wird derzeit noch nicht als Gangwechsel ausgewertet.',
    'status_incomplete' => 'Die FIT-Datei enthält Schaltmeldungen, deren Gangwerte oder Zeitangaben fehlen oder nicht eindeutig zugeordnet werden können.',
    'recording_hint' => 'Prüfe am Radcomputer, ob die elektronische Schaltung als Sensor verbunden ist und während der Fahrt Gangwerte anzeigt. SRAM am Rad allein bestätigt noch keine Aufzeichnung in dieser Datei.',
    'limits' => 'Die Werte zeigen den gemeldeten Gang nach dem Schaltvorgang. Fehlende Angaben bleiben offen. Daraus wird keine Nutzungsdauer je Gang geschätzt; die Aufzeichnung kann unvollständig sein.',
    'ignored' => ':count unvollständige oder nicht eindeutig zuordenbare Schaltmeldungen wurden ausgelassen.',
    'truncated' => 'Der Verlauf zeigt die ersten 200 aufgezeichneten Gangwechsel. Die Zähler berücksichtigen alle.',
    'reprocess' => 'FIT-Datei erneut auswerten',
    'reprocess_hint' => 'Werte die gespeicherte Originaldatei mit dem aktuellen Parser erneut aus. Messwerte und Schaltinformationen werden aktualisiert; deine Notizen und RPE-Bewertung bleiben erhalten.',
    'no_file' => 'Für diese Fahrt ist keine auswertbare Originaldatei mehr gespeichert.',
    'failed' => 'Die Originaldatei konnte nicht ausgewertet werden. Die bisherige Auswertung bleibt erhalten.',
    'updated' => 'Originaldatei erneut ausgewertet. Messwerte und Schaltinformationen sind aktualisiert.',
];
