<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Auswahl;

use Core\Mailer;

/** E-Mails der Bildauswahl (reiner Text): Einladung an die Kundin bzw. den Kunden, Hinweis an das Studio, Bestätigung */
final class Mail
{
    /** Einladung mit Link (die PIN steht nie in der E-Mail – sie geht auf einem zweiten Weg) → Fehlertext oder null */
    public static function invite(array $s, string $note = ''): ?string
    {
        if (!filter_var((string) $s['email'], FILTER_VALIDATE_EMAIL)) return __('Für dieses Shooting ist keine gültige E-Mail-Adresse eingetragen.');
        return Auswahl::inLang($s['lang'] ?: null, function () use ($s, $note): ?string {
            $lines = [($s['client'] !== '' ? lt('Guten Tag {name},', ['name' => $s['client']]) : lt('Guten Tag,')), '',
                lt('Ihre Bilder aus dem Shooting „{title}“ sind bereit. Bitte wählen Sie Ihre Favoriten aus:', ['title' => $s['title']]), '',
                Auswahl::link($s, '', true), ''];
            if (trim($note) !== '') array_push($lines, trim($note), '');
            if ((int) $s['max_picks'] > 0) $lines[] = lt('In Ihrem Paket sind {n} Dateien enthalten.', ['n' => (int) $s['max_picks']]);
            if ((string) $s['deadline'] !== '') $lines[] = lt('Bitte schicken Sie Ihre Auswahl bis zum {date} ab.', ['date' => fmt()->date((string) $s['deadline'])]);
            if ((string) $s['pin_hash'] !== '') $lines[] = lt('Zum Öffnen brauchen Sie eine PIN – Sie erhalten sie separat.');
            array_push($lines, '', site_name());
            $err = Mailer::send(lt('Ihre Bilder zur Auswahl – {title}', ['title' => $s['title']]), implode("\n", $lines), [(string) $s['email']]);
            if ($err === null) Repo::update((int) $s['id'], ['invited_at' => now()]);
            return $err;
        });
    }

    /** Auswahl abgeschickt: Hinweis an das Studio (mit Dateinamen) und Bestätigung an die Kundin bzw. den Kunden */
    public static function submitted(array $s): void
    {
        $sel = Repo::selection($s);
        $picked = array_values(array_filter($sel, fn($m) => $m['_picked']));
        $names = Repo::fileNames($picked);
        $text = __('{client} hat die Auswahl für „{title}“ abgeschickt: {n} Dateien.', ['client' => $s['client'] ?: __('Die Kundin bzw. der Kunde'), 'title' => $s['title'], 'n' => count($picked)]) . "\n\n"
            . ((string) $s['message'] !== '' ? __('Nachricht:') . "\n" . $s['message'] . "\n\n" : '')
            . __('Dateinamen:') . "\n" . implode(', ', $names) . "\n\n";
        foreach ($sel as $m) {
            if ($m['_note'] !== '') $text .= '- ' . pathinfo((string) ($m['original_name'] ?: $m['file']), PATHINFO_FILENAME) . ': ' . $m['_note'] . "\n";
        }
        $text .= "\n" . __('Ansehen: {url}', ['url' => abs_url(url(Auswahl::ADMIN . '/' . (int) $s['id']))]);
        Mailer::send(__('Auswahl eingegangen: {title}', ['title' => $s['title']]), $text, Auswahl::notifyTo(),
            filter_var((string) $s['email'], FILTER_VALIDATE_EMAIL) ? ['reply_to' => (string) $s['email']] : []);

        if (filter_var((string) $s['email'], FILTER_VALIDATE_EMAIL)) {
            Auswahl::inLang($s['lang'] ?: null, fn() => Mailer::send(lt('Ihre Auswahl ist eingegangen – {title}', ['title' => $s['title']]),
                ($s['client'] !== '' ? lt('Guten Tag {name},', ['name' => $s['client']]) : lt('Guten Tag,')) . "\n\n"
                . lt('vielen Dank – Ihre Auswahl ({n} Dateien) ist bei uns eingegangen. Wir melden uns, sobald die Bilder fertig sind.', ['n' => count($picked)]) . "\n\n"
                . lt('Ihre Auswahl ansehen: {link}', ['link' => Auswahl::link($s, '', true)]) . "\n\n" . site_name(),
                [(string) $s['email']]));
        }
    }
}
