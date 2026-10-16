# Changelog – Bildauswahl

## 0.3.0 – 2026-10-16

- **Schutz bis zur Freigabe:** Kunden sehen im Raster kleine Vorschauen (560 px, ohne Wasserzeichen) und in der Großansicht höchstens 1800 px mit eingerechnetem Wasserzeichen, Videos als 720p-Vorschau mit Wasserzeichen (ffmpeg, im Hintergrund und als geplante Aufgabe `auswahl:videos`). Die Originale sind ohne Freigabe auch für entsperrte Sitzungen gesperrt; Rechtsklick und Ziehen auf Bildern sind abgeschaltet.
- **Freigabe** im Shooting („Freigegeben: Originale ohne Wasserzeichen“): Ansicht ohne Wasserzeichen und Herunterladen der Originale. Text des Wasserzeichens unter Einstellungen.
- Großansicht: Bilder gleiten beim Blättern mit Überblendung, öffnen mit leichtem Zoom; schnelles Blättern hinterlässt keine Reste.

## 0.2.0 – 2026-10-16

- **Geschützter Ordner je Shooting:** Neue Shootings bekommen eine eigene Sammlung im geschützten Pool „Shootings“ der Website – die Mediathek bleibt frei. Fotos und Videos direkt im Shooting hochladen (Ablagefläche, in Stücken, mit Fortschritt), entfernen, nach Dateinamen sortieren. Löschen eines Shootings löscht seine Dateien.
- **Zugriff nur mit Link:** Dateien des Pools liefert der Kern nur an Sitzungen mit entsperrtem Shooting (Extension::mediaAccess); eine weitergegebene Bildadresse allein genügt nicht mehr.
- Vorhandene Sammlungen der Mediathek bleiben als Alternative wählbar.

## 0.1.0 – 2026-10-16

- Erste Fassung: Shootings aus Sammlungen der Mediathek per geheimem Link (optional mit PIN), Auswahl per Herz mit Paketgrenze und Frist, Notizen je Bild, Großansicht mit Tastatur und Wischen, Herunterladen der Originale (Schalter), verbindliches Abschicken mit Nachricht.
- Verwaltung: Karten mit Stand, Einladung per E-Mail, Auswahl mit Notizen, Dateinamen zum Kopieren für Lightroom und Capture One, CSV, Wieder öffnen, Abschließen, neuer Link, Zurücksetzen.
- Mitteilungen: E-Mail an das Studio (Antwortadresse = Kunde) und Bestätigung an den Kunden, Push-Ereignis `auswahl.submitted`, Kachel in der Übersicht, Hinweis in den Datei-Informationen der Mediathek.
- Handbuch und Entwicklerhandbuch, Englisch für Verwaltung und Kundenseiten, Selbsttest `auswahl:selftest`.
