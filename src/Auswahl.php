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
        if ((string) $shoot['pin_hash'] === '' && (string) ($shoot['pool'] ?? '') === '') return true;
        if (!app()->session->started()) return false;
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

    // ------------------------------------------------------------------ Eigener geschützter Pool

    /** Kurzname des Pools dieser Website (je Website ein eigener Pool, z. B. „aw-default“) */
    public static function poolKey(): string
    {
        return 'aw-' . substr(trim((string) preg_replace('~[^a-z0-9-]+~', '-', strtolower(site()->key)), '-') ?: 'site', 0, 29);
    }

    /**
     * Geschützten Pool „Shootings“ dieser Website anlegen bzw. freigeben: Dateien außerhalb von public/, Adresse /geschuetzt/…,
     * nur für diese Website (nutzen und pflegen), Eigentümer „auswahl“ (andere Erweiterungen wie members greifen nicht darauf zu).
     */
    public static function ensurePool(): string
    {
        $key = self::poolKey();
        $site = site()->key;
        if (!is_file(\Core\MediaPools::dir($key) . '/pool.json')) {
            \Core\MediaPools::create($key, 'Shootings (Bildauswahl)', [$site], ['protected' => true, 'editors' => [$site], 'owner' => self::NAME]);
        } elseif (!in_array($site, (array) \Core\MediaPools::meta($key)['sites'], true)) {
            \Core\MediaPools::update($key, (string) \Core\MediaPools::meta($key)['label'], array_merge((array) \Core\MediaPools::meta($key)['sites'], [$site]), [$site]);
        }
        return $key;
    }

    /**
     * Zugriff auf Dateien des eigenen Pools (Extension::mediaAccess): nur, wenn in dieser Sitzung ein Shooting entsperrt ist,
     * zu dessen Sammlung die Datei gehört (Original, Größen, Vorschaubild eines Videos). Andere Pools: nicht zuständig.
     */
    public static function mediaAllowed(array $ctx, Request $r): bool
    {
        $pool = (string) ($ctx['pool'] ?? '');
        if (!str_starts_with($pool, 'aw-')) return false;
        // Sitzung nur fortsetzen, wenn es schon eine gibt (kein neues Cookie für fremde Abrufe)
        if (!app()->session->started()) {
            if (!app()->session->hasCookie()) return false;
            self::startSession($r);
        }
        $ok = app()->session->get('auswahl_ok');
        if (!is_array($ok) || !$ok) return false;
        if (!preg_match('~(?:^|/)([0-9a-f]{16})~', (string) ($ctx['path'] ?? ''), $m)) return false;
        $ids = array_map('intval', array_keys($ok));
        $shoots = app()->db->fetchAll('SELECT * FROM auswahl_shoots WHERE pool = ? AND status <> ? AND id IN (' . implode(',', $ids) . ')', [$pool, 'closed']);
        $db = \Core\MediaPools::db($pool);
        foreach ($shoots as $s) {
            if (!self::unlocked($s)) continue;
            $hit = $db->fetchValue("SELECT 1 FROM media_collection_items i JOIN media m ON m.id = i.media_id
                WHERE i.collection_id = ? AND m.deleted_at IS NULL AND (m.file LIKE ? OR m.variants_json LIKE ?) LIMIT 1",
                [(int) $s['collection_id'], '%' . $m[1] . '%', '%' . $m[1] . '%']);
            if ($hit) return true;
        }
        return false;
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
