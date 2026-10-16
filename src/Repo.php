<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Auswahl;

use Core\Db\Table;
use Core\Media;

/**
 * Daten: Shootings (auswahl_shoots) und die Auswahl der Kundin bzw. des Kunden je Datei (auswahl_picks).
 * Die Dateien eines Shootings sind eine Sammlung der Mediathek – hochladen, sortieren, ersetzen wie gewohnt.
 */
final class Repo
{
    public const STATUS = ['open', 'submitted', 'closed'];

    public static function tables(): array
    {
        return [
            'auswahl_shoots' => fn(Table $t) => $t->id()
                ->column('title', 'string', ['length' => 160, 'null' => false])
                ->column('client', 'string', ['length' => 160, 'default' => ''])
                ->column('email', 'string', ['length' => 191, 'default' => ''])
                ->column('intro', 'text')
                ->column('collection_id', 'int', ['default' => 0])
                ->column('cover_id', 'int', ['default' => 0])
                ->column('shoot_date', 'string', ['length' => 10, 'default' => ''])
                ->column('token', 'string', ['length' => 64, 'null' => false])
                ->column('pin_hash', 'string', ['length' => 255, 'default' => ''])
                ->column('max_picks', 'int', ['default' => 0])
                ->column('deadline', 'string', ['length' => 10, 'default' => ''])
                ->column('allow_download', 'int', ['default' => 0])
                ->column('allow_comments', 'int', ['default' => 1])
                ->column('status', 'string', ['length' => 12, 'default' => 'open'])
                ->column('message', 'text')
                ->column('lang', 'string', ['length' => 10, 'default' => ''])
                ->column('views', 'int', ['default' => 0])
                ->column('created_at', 'datetime')->column('updated_at', 'datetime')
                ->column('submitted_at', 'datetime')->column('last_seen_at', 'datetime')->column('invited_at', 'datetime')
                ->unique('token')->index('status'),
            'auswahl_picks' => fn(Table $t) => $t->id()
                ->column('shoot_id', 'int', ['null' => false])
                ->column('media_id', 'int', ['null' => false])
                ->column('picked', 'int', ['default' => 0])
                ->column('note', 'text')
                ->column('updated_at', 'datetime')
                ->unique(['shoot_id', 'media_id'])->index('shoot_id'),
        ];
    }

    private static function db(): \Core\Database
    {
        return app()->db;
    }

    // ------------------------------------------------------------------ Shootings

    /** @return list<array> mit 'count' (Dateien) und 'picked' (ausgewählt) */
    public static function shoots(string $status = ''): array
    {
        $where = in_array($status, self::STATUS, true) ? 'WHERE s.status = ?' : '';
        return self::db()->fetchAll("SELECT s.*,
                (SELECT COUNT(*) FROM media_collection_items i JOIN media m ON m.id = i.media_id AND m.deleted_at IS NULL WHERE i.collection_id = s.collection_id) AS count,
                (SELECT COUNT(*) FROM auswahl_picks p WHERE p.shoot_id = s.id AND p.picked = 1) AS picked
            FROM auswahl_shoots s $where ORDER BY CASE s.status WHEN 'submitted' THEN 0 WHEN 'open' THEN 1 ELSE 2 END, COALESCE(s.updated_at, s.created_at) DESC",
            $where ? [$status] : []);
    }

    public static function find(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM auswahl_shoots WHERE id = ?', [$id]) ?: null;
    }

    /** Shooting zum Link (Vergleich in konstanter Zeit über den Index-Treffer) */
    public static function byToken(string $token): ?array
    {
        if (!preg_match('~^[A-Za-z0-9_-]{20,64}$~', $token)) return null;
        $s = self::db()->fetch('SELECT * FROM auswahl_shoots WHERE token = ?', [$token]);
        return $s && hash_equals((string) $s['token'], $token) ? $s : null;
    }

    public static function create(array $data): int
    {
        $now = now();
        return self::db()->insert('auswahl_shoots', $data + ['token' => self::newToken(), 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
    }

    public static function update(int $id, array $data): void
    {
        self::db()->update('auswahl_shoots', $data + ['updated_at' => now()], 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        self::db()->query('DELETE FROM auswahl_picks WHERE shoot_id = ?', [$id]);
        self::db()->query('DELETE FROM auswahl_shoots WHERE id = ?', [$id]);
    }

    public static function newToken(): string
    {
        return random_token(24);
    }

    /** @return array{open: int, submitted: int, closed: int} */
    public static function counts(): array
    {
        $out = ['open' => 0, 'submitted' => 0, 'closed' => 0];
        foreach (self::db()->fetchAll('SELECT status, COUNT(*) AS n FROM auswahl_shoots GROUP BY status') as $r) {
            if (isset($out[$r['status']])) $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    // ------------------------------------------------------------------ Dateien und Auswahl

    /** Bilder und Videos der Sammlung in ihrer Reihenfolge (ohne gelöschte, ohne andere Dateiarten) */
    public static function items(array $shoot): array
    {
        $cid = (int) $shoot['collection_id'];
        if (!$cid) return [];
        return self::db()->fetchAll("SELECT m.* FROM media_collection_items i JOIN media m ON m.id = i.media_id
            WHERE i.collection_id = ? AND m.deleted_at IS NULL AND (m.mime LIKE 'image/%' OR m.mime LIKE 'video/%') AND m.mime <> 'image/svg+xml'
            ORDER BY i.sort, m.id", [$cid]);
    }

    /** Gehört die Datei zum Shooting? */
    public static function hasItem(array $shoot, int $mediaId): bool
    {
        return (bool) self::db()->fetchValue("SELECT 1 FROM media_collection_items i JOIN media m ON m.id = i.media_id
            WHERE i.collection_id = ? AND i.media_id = ? AND m.deleted_at IS NULL AND (m.mime LIKE 'image/%' OR m.mime LIKE 'video/%')",
            [(int) $shoot['collection_id'], $mediaId]);
    }

    /** @return array<int, array{picked: bool, note: string}> media_id → Auswahl */
    public static function picks(int $shootId): array
    {
        $out = [];
        foreach (self::db()->fetchAll('SELECT media_id, picked, note FROM auswahl_picks WHERE shoot_id = ?', [$shootId]) as $r) {
            $out[(int) $r['media_id']] = ['picked' => (bool) $r['picked'], 'note' => (string) $r['note']];
        }
        return $out;
    }

    public static function pickedCount(int $shootId): int
    {
        return (int) self::db()->fetchValue('SELECT COUNT(*) FROM auswahl_picks WHERE shoot_id = ? AND picked = 1', [$shootId]);
    }

    /** Auswahl bzw. Notiz einer Datei setzen (Zeile entsteht bei Bedarf) */
    public static function setPick(int $shootId, int $mediaId, ?bool $picked = null, ?string $note = null): void
    {
        $db = self::db();
        $row = $db->fetch('SELECT id FROM auswahl_picks WHERE shoot_id = ? AND media_id = ?', [$shootId, $mediaId]);
        $data = ['updated_at' => now()];
        if ($picked !== null) $data['picked'] = $picked ? 1 : 0;
        if ($note !== null) $data['note'] = $note;
        if ($row) $db->update('auswahl_picks', $data, 'id = :id', ['id' => (int) $row['id']]);
        else $db->insert('auswahl_picks', $data + ['shoot_id' => $shootId, 'media_id' => $mediaId, 'picked' => (int) ($data['picked'] ?? 0), 'note' => (string) ($data['note'] ?? '')]);
    }

    /** Ausgewählte und kommentierte Dateien (für Verwaltung, E-Mail, Export), in der Reihenfolge der Sammlung */
    public static function selection(array $shoot): array
    {
        $picks = self::picks((int) $shoot['id']);
        $out = [];
        foreach (self::items($shoot) as $m) {
            $p = $picks[(int) $m['id']] ?? null;
            if ($p && ($p['picked'] || $p['note'] !== '')) $out[] = $m + ['_picked' => $p['picked'], '_note' => $p['note']];
        }
        return $out;
    }

    /** Dateinamen ohne Endung (z. B. für die Filtersuche in Lightroom: „DSC_0012, DSC_0045“) */
    public static function fileNames(array $items): array
    {
        return array_values(array_map(fn($m) => (string) pathinfo((string) ($m['original_name'] ?: $m['file']), PATHINFO_FILENAME), $items));
    }

    /** Titelbild: gewähltes Bild, sonst das erste Bild der Sammlung */
    public static function cover(array $shoot): ?array
    {
        if ((int) $shoot['cover_id'] && ($m = Media::find((int) $shoot['cover_id'])) && str_starts_with((string) $m['mime'], 'image/')) return $m;
        foreach (self::items($shoot) as $m) if (str_starts_with((string) $m['mime'], 'image/')) return $m;
        return null;
    }
}
