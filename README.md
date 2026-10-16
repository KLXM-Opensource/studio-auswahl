# Bildauswahl für KLXM Studio (`auswahl`)

Für Fotografinnen und Fotografen: Ein Shooting per geheimem Link zeigen, Kundinnen und Kunden wählen Fotos und Videos aus,
schreiben Notizen und schicken die Auswahl verbindlich ab – ohne Konto, ohne App, im Design der eigenen Website.
Lizenz: MIT (`LICENSE`). Paket: `klxm/studio-auswahl`. Für [KLXM Studio](https://studio.klxm.de) ≥ 1.0.0.

## Funktionen

| | |
|---|---|
| Shooting | Dateien = eine Sammlung der Mediathek (Reihenfolge, Dateinamen, Bilder und Videos). Titel, Datum, Begrüßung, Kunde mit E-Mail, Sprache. |
| Regeln | Dateien im Paket (Obergrenze), Frist („Auswahl bis“), optionale PIN (4–12 Zeichen, getrennt vom Link weitergeben), Notizen je Bild, Herunterladen der Originale. |
| Link | `/auswahl/{token}` (32 Zeichen, zufällig). „Neuen Link erzeugen“ macht den alten ungültig; eine neue PIN sperrt offene Sitzungen. Einladung per E-Mail (die PIN steht nie darin). |
| Für Kunden | Raster in Originalformaten mit Nummer und Dateiname, Herz zum Auswählen, Zähler „3 / 20“ mit Fortschritt, Filter „Ausgewählt“ und „Mit Notiz“, Großansicht (Pfeiltasten, Wischen, `P` wählt aus, Notiz, Herunterladen), „Auswahl abschicken“ mit Nachricht. Alles wird sofort gespeichert. |
| Für das Studio | Übersicht als Karten mit Stand, Detail mit Link, Einladung, Auswahl samt Notizen, **Dateinamen zum Kopieren** (Filter in Lightroom, Capture One, Photo Mechanic), CSV, Wieder öffnen, Abschließen, Zurücksetzen. E-Mail bei eingegangener Auswahl (Antwortadresse = Kunde), Push-Mitteilung, Kachel in der Übersicht. |

Barrierefrei (WCAG 2.2 AA): Schaltflächen mit `aria-pressed`, `<dialog>` mit Fokus zurück, Tastatur, `prefers-reduced-motion`,
`forced-colors`. CSP-fest (keine Inline-Skripte oder -Stile). Farben aus dem Kit-Vertrag (`--kit-accent`, `--kit-ink`, `--kit-bg`).

## Installation

```bash
composer require klxm/studio-auswahl
php bin/console extensions:publish && php bin/console migrate --all
```

Aktivieren je Website: Administration → Funktionen & Erweiterungen oder `'extensions' => ['auswahl']` in `config/sites/{key}.php`.
Recht „Bildauswahl verwalten“ (`auswahl.manage`) an die passenden Rollen geben. Selbsttest: `php bin/console auswahl:selftest --site=…`.

## Sicherheit

- Link-Token mit 24 Zufallsbytes, Vergleich in konstanter Zeit; PIN nur als `password_hash`, höchstens 8 Versuche je Anschluss und Shooting in 15 Minuten.
- Alle Änderungen nur mit CSRF-Token der Sitzung, nach Entsperrung, für Dateien der Sammlung, innerhalb von Frist, Paket und Status; Begrenzung je Anschluss.
- Seiten privat, nie im Seiten-Cache, `noindex`, `Referrer-Policy: no-referrer`. Bilder haben zufällige Adressen; wer eine einzelne Bildadresse weitergibt, gibt dieses Bild weiter – für vertrauliche Shootings eine PIN setzen.

## Doku

Handbuch → „Bildauswahl“, Entwicklerhandbuch → „Bildauswahl (Erweiterung)“ (beide aus `docs/`).
