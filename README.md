# Bildauswahl für KLXM Studio (`auswahl`)

Für Fotografinnen und Fotografen: Ein Shooting per geheimem Link zeigen, Kundinnen und Kunden wählen Fotos und Videos aus,
schreiben Notizen und schicken die Auswahl verbindlich ab – ohne Konto, ohne App, im Design der eigenen Website.
Lizenz: MIT (`LICENSE`). Paket: `klxm/studio-auswahl`. Für [KLXM Studio](https://studio.klxm.de) ≥ 1.0.0.

## Funktionen

| | |
|---|---|
| Shooting | Eigener **geschützter Ordner** je Shooting (Pool „Shootings“ der Website, außerhalb von `public/`): Fotos und Videos direkt im Shooting hochladen (in Stücken, auch große Videos), entfernen, nach Dateinamen sortieren – die Mediathek der Website bleibt frei. Alternativ eine vorhandene Sammlung der Mediathek. Titel, Datum, Begrüßung, Kunde mit E-Mail, Sprache. |
| Regeln | Dateien im Paket (Obergrenze), Frist („Auswahl bis“), optionale PIN (4–12 Zeichen, getrennt vom Link weitergeben), Notizen je Bild. |
| Schutz bis zur Freigabe | Kunden sehen nur **Bildschirmfassungen** (höchstens 1800 px, WebP) mit **Wasserzeichen**, ins Bild gerechnet; Videos als 720p-Vorschau mit Wasserzeichen (ffmpeg, im Hintergrund). Nie das Original: Die Originale im geschützten Ordner sind ohne Freigabe gesperrt, Rechtsklick und Ziehen sind abgeschaltet. Nur Markieren und Notizen. |
| Freigabe | Schalter „Freigegeben“ im Shooting: Ansicht ohne Wasserzeichen, Originale herunterladen – z. B. für die Abgabe der fertigen Bilder. Text des Wasserzeichens unter Einstellungen. |
| Link | `/auswahl/{token}` (32 Zeichen, zufällig). „Neuen Link erzeugen“ macht den alten ungültig; eine neue PIN sperrt offene Sitzungen. Einladung per E-Mail (die PIN steht nie darin). |
| Für Kunden | Raster in Originalformaten mit Nummer und Dateiname, Herz zum Auswählen, Zähler „3 / 20“ mit Fortschritt, Filter „Ausgewählt“ und „Mit Notiz“, Großansicht mit Wechseleffekten (Pfeiltasten, Wischen, `P` wählt aus, Notiz; Herunterladen erst nach Freigabe), „Auswahl abschicken“ mit Nachricht. Alles wird sofort gespeichert. |
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
- Seiten privat, nie im Seiten-Cache, `noindex`, `Referrer-Policy: no-referrer`.
- Dateien im geschützten Ordner (`/geschuetzt/aw-{website}/…`) liefert der Kern nur an die Redaktion oder an Sitzungen, in denen ein Shooting entsperrt ist, zu dem die Datei gehört (`Extension::mediaAccess`) – eine weitergegebene Bildadresse funktioniert ohne den Link nicht. Andere Erweiterungen (z. B. members) greifen auf diesen Pool nicht zu (`pool.json` `owner`). Shootings aus einer Sammlung der Mediathek haben öffentliche, zufällige Adressen.

## Doku

Handbuch → „Bildauswahl“, Entwicklerhandbuch → „Bildauswahl (Erweiterung)“ (beide aus `docs/`).
