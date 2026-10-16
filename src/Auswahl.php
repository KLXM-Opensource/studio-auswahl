<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Auswahl;

use Core\Http\Request;

/** Gemeinsame Konstanten und Regeln der Bildauswahl */
final class Auswahl
{
    public const NAME = 'auswahl';
    public const FEATURE = 'auswahl';
    public const PERM = 'auswahl.manage';
    public const BASE = '/auswahl';
    public const ADMIN = '/admin/auswahl';
    /** PIN-Versuche je Anschluss und Shooting in 15 Minuten */
    public const PIN_TRIES = 8;
    public const NOTE_MAX = 500;

    public static function asset(string $path): string
    {
        $x = \Core\Extensions::active()[self::NAME] ?? null;
        return $x ? $x->asset($path) : '';
    }

    /** Adresse für Kundinnen und Kunden (Sprache des Shootings als ?lang=xx, Standardsprache ohne) */
    public static function link(array $shoot, string $sub = '', bool $absolute = false): string
    {
        $path = self::BASE . '/' . $shoot['token'] . ($sub !== '' ? '/' . $sub : '');
        $lang = (string) ($shoot['lang'] ?? '');
        $q = $lang !== '' && $lang !== \Core\Lang::default() && \Core\Lang::valid($lang) ? '?lang=' . $lang : '';
        if (!$absolute) return url($path) . $q;
        $base = trusted_site_url();
        return ($base !== null ? rtrim($base, '/') : '') . url($path) . $q;
    }

    public static function startSession(Request $r): void
    {
        $s = app()->session;
        if (!$s->started()) $s->start($r->isSecure());
    }

    /** Ist das Shooting in dieser Sitzung entsperrt (PIN eingegeben bzw. ohne PIN geöffnet)? */
    public static function unlocked(array $shoot): bool
    {
        if ((string) $shoot['pin_hash'] === '') return true;
        $ok = app()->session->get('auswahl_ok');
        return is_array($ok) && hash_equals((string) ($ok[(int) $shoot['id']] ?? ''), self::seal($shoot));
    }

    public static function unlock(array $shoot): void
    {
        $ok = app()->session->get('auswahl_ok');
        $ok = is_array($ok) ? $ok : [];
        $ok[(int) $shoot['id']] = self::seal($shoot);
        app()->session->set('auswahl_ok', $ok);
    }

    /** Siegel aus Link und PIN: neuer Link oder neue PIN sperren offene Sitzungen wieder */
    private static function seal(array $shoot): string
    {
        return hash_hmac('sha256', $shoot['token'] . '|' . $shoot['pin_hash'], app()->key());
    }

    /** Frist abgelaufen? (Frist gilt bis Ende des Tages) */
    public static function expired(array $shoot): bool
    {
        $d = (string) $shoot['deadline'];
        return $d !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $d) && date('Y-m-d') > $d;
    }

    /** Darf die Kundin bzw. der Kunde noch auswählen? */
    public static function editable(array $shoot): bool
    {
        return $shoot['status'] === 'open' && !self::expired($shoot);
    }

    /** Kann das Shooting überhaupt aufgerufen werden? (geschlossen = Link zeigt nur einen Hinweis) */
    public static function viewable(array $shoot): bool
    {
        return $shoot['status'] !== 'closed';
    }

    public static function statusLabels(): array
    {
        return ['open' => __('Offen'), 'submitted' => __('Auswahl eingegangen'), 'closed' => __('Abgeschlossen')];
    }

    /** PIN prüfen: 4–12 Ziffern oder Buchstaben (leer = ohne PIN) */
    public static function validPin(string $pin): bool
    {
        return $pin === '' || (bool) preg_match('~^[A-Za-z0-9]{4,12}$~', $pin);
    }

    /** Empfänger für Hinweise an das Studio (Einstellung, sonst Empfänger der Website) */
    public static function notifyTo(): ?array
    {
        $to = array_values(array_filter(array_map('trim', explode(',', (string) app()->settings->get('auswahl.notify', ''))), fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        return $to ?: null;
    }

    /** Code in der Sprache des Shootings ausführen (E-Mails an Kunden aus der Verwaltung) */
    public static function inLang(?string $lang, callable $fn): mixed
    {
        $old = app()->lang;
        if ($lang && \Core\Lang::valid($lang)) app()->lang = $lang;
        try {
            return $fn();
        } finally {
            app()->lang = $old;
        }
    }
}
