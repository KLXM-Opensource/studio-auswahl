<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
/**
 * Erweiterung „auswahl“: Fotografinnen und Fotografen zeigen ein Shooting per geheimem Link (optional mit PIN), Kundinnen und
 * Kunden wählen Fotos und Videos aus, schreiben Notizen und schicken die Auswahl ab. Dateien = eine Sammlung der Mediathek.
 *
 * Aktivieren je Website: Administration → Funktionen & Erweiterungen oder 'extensions' => ['auswahl'].
 * Recht: „Bildauswahl verwalten“ (auswahl.manage). Doku: README.md.
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\Auswahl\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\Auswahl\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

use Klxm\Auswahl\AdminController;
use Klxm\Auswahl\Auswahl;
use Klxm\Auswahl\Repo;
use Klxm\Auswahl\SiteController;

return [
    'name' => 'auswahl',
    'label' => 'Bildauswahl – Kunden wählen Fotos und Videos aus',
    'version' => '0.3.0',
    'requires' => '>=1.0.0',
    'description' => 'Shootings per geheimem Link zeigen (optional mit PIN): Kundinnen und Kunden wählen Fotos und Videos aus, schreiben Notizen und schicken die Auswahl ab.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'provides' => ['Menüpunkt „Bildauswahl“ (Shootings, Einstellungen)', 'Kundenseiten unter /auswahl/{link}', 'Recht „Bildauswahl verwalten“',
        'Mitteilung „Auswahl eingegangen“ (E-Mail, Push)'],
    'commands' => ['auswahl:selftest', 'auswahl:videos'],
    // Geplante Aufgabe (Core\Cron): fehlende Videovorschauen mit Wasserzeichen erzeugen
    'schedule' => [
        'videos' => ['label' => 'Bildauswahl: Videovorschauen erzeugen', 'command' => 'auswahl:videos', 'cron' => '*/5 * * * *', 'scope' => 'site', 'timeout' => 900],
    ],
    'usage' => function (): ?string {
        try {
            $c = Repo::counts();
        } catch (\Throwable) {
            return null;
        }
        $n = array_sum($c);
        return $n ? __('{n} Shootings', ['n' => $n]) . ($c['submitted'] ? ' · ' . __('{n} Auswahl eingegangen', ['n' => $c['submitted']]) : '') : null;
    },
    'boot' => function (Core\Extension $x): void {
        $x->feature(Auswahl::FEATURE, 'Bildauswahl: Kunden wählen Fotos und Videos aus', [Auswahl::PERM]);
        $x->permissions('Bildauswahl', [Auswahl::PERM => 'Shootings anlegen, Links teilen, Auswahl ansehen']);
        foreach (Repo::tables() as $name => $define) $x->table($name, $define);
        $x->pushEvent('auswahl.submitted', 'Bildauswahl eingegangen', Auswahl::PERM, ['help' => 'Eine Kundin oder ein Kunde hat die Auswahl abgeschickt.']);

        $x->adminPage(['href' => Auswahl::ADMIN, 'label' => 'Bildauswahl', 'kind' => 'content', 'icon' => 'camera', 'perm' => Auswahl::PERM,
            'feature' => Auswahl::FEATURE, 'description' => 'Shootings zeigen, Kunden wählen Fotos und Videos aus']);
        $x->adminAssets(fn(string $view) => $view === 'auswahl' ? ['css/auswahl-admin.css', 'js/auswahl-admin.js'] : []);

        // Übersicht (/admin): eingegangene Auswahl als Kachel
        $x->dashboard(function (array $user): array {
            if (!\Core\Features::on(Auswahl::FEATURE) || !can(Auswahl::PERM)) return [];
            $c = Repo::counts();
            if (!$c['submitted'] && !$c['open']) return [];
            return ['tiles' => [['key' => 'auswahl-eingegangen', 'label' => __('Bildauswahl eingegangen'), 'value' => (string) $c['submitted'],
                'text' => __('{n} offen', ['n' => $c['open']]), 'href' => Auswahl::ADMIN . '?status=submitted']]];
        });

        // Geschützter Pool „Shootings“: Dateien nur für Sitzungen mit entsperrtem Shooting (Redaktion prüft der Kern)
        $x->mediaAccess(fn(array $ctx, Core\Http\Request $r): bool => Auswahl::mediaAllowed($ctx, $r));

        // Mediathek → Informationen einer Datei: in welchen Shootings sie steckt
        $x->mediaPanel(function (array $m): array {
            if (!\Core\Features::on(Auswahl::FEATURE)) return [];
            $ids = \Core\Media::collectionIds((int) ($m['id'] ?? 0));
            if (!$ids) return [];
            $rows = app()->db->fetchAll('SELECT id, title FROM auswahl_shoots WHERE collection_id IN (' . implode(',', array_map('intval', $ids)) . ')');
            if (!$rows) return [];
            return ['title' => __('Bildauswahl'), 'lines' => array_map(fn($r) => (string) $r['title'], $rows),
                'actions' => array_map(fn($r) => ['label' => __('Shooting öffnen'), 'href' => url(Auswahl::ADMIN . '/' . (int) $r['id'])], array_slice($rows, 0, 3))];
        }, Auswahl::PERM);

        $x->routes(function (Core\Http\Router $r): void {
            $s = SiteController::class;
            $b = Auswahl::BASE . '/{token}';
            $r->get($b, [$s, 'show']);
            $r->post($b . '/pin', [$s, 'pin']);
            $r->post($b . '/wahl', [$s, 'pick']);
            $r->post($b . '/notiz', [$s, 'note']);
            $r->post($b . '/abschicken', [$s, 'submit']);
            $r->get($b . '/datei/{id}', [$s, 'download']);
            $r->get($b . '/bild/{id}/{size}', [$s, 'image']);
            $r->get($b . '/film/{id}', [$s, 'film']);

            // Verwaltung: Recht + CSRF prüft der Router
            $c = AdminController::class;
            $p = Auswahl::PERM;
            $a = Auswahl::ADMIN;
            $r->get($a, [$c, 'index'], $p);
            $r->post($a, [$c, 'store'], $p);
            $r->get($a . '/neu', [$c, 'create'], $p);
            $r->get($a . '/einstellungen', [$c, 'settings'], $p);
            $r->post($a . '/einstellungen', [$c, 'saveSettings'], $p);
            $r->get($a . '/{id}', [$c, 'show'], $p);
            $r->get($a . '/{id}/bearbeiten', [$c, 'edit'], $p);
            $r->post($a . '/{id}/bearbeiten', [$c, 'update'], $p);
            $r->post($a . '/{id}/senden', [$c, 'send'], $p);
            $r->post($a . '/{id}/status', [$c, 'status'], $p);
            $r->post($a . '/{id}/neuer-link', [$c, 'renew'], $p);
            $r->post($a . '/{id}/zuruecksetzen', [$c, 'reset'], $p);
            $r->post($a . '/{id}/loeschen', [$c, 'delete'], $p);
            $r->get($a . '/{id}/auswahl.csv', [$c, 'csv'], $p);
            $r->post($a . '/{id}/upload/teil', [$c, 'chunk'], $p);
            $r->post($a . '/{id}/upload/fertig', [$c, 'finish'], $p);
            $r->post($a . '/{id}/datei/{mid}/entfernen', [$c, 'removeFile'], $p);
            $r->post($a . '/{id}/sortieren', [$c, 'sort'], $p);
        });

        $x->docs('manual', ['bildauswahl' => ['title' => 'Bildauswahl', 'after' => 'medien', 'file' => __DIR__ . '/docs/manual.php']]);
        $x->docs('technical', ['bildauswahl' => ['title' => 'Bildauswahl (Erweiterung): Kunden wählen Fotos aus', 'part' => 3, 'after' => 'live', 'file' => __DIR__ . '/docs/technical.php']]);

        // Videovorschauen nach der Antwort einer Verwaltungsseite (nur wenn der Merker gesetzt ist – billige Prüfung)
        $x->afterAdminResponse(fn() => is_file(Klxm\Auswahl\Preview::flag(false)) ? fn() => Klxm\Auswahl\Preview::work(1) : null);
        $x->command('auswahl:videos', 'Bildauswahl: fehlende Videovorschauen (720p, Wasserzeichen) erzeugen', function (array $args): int {
            $n = Klxm\Auswahl\Preview::work(20);
            echo $n . " Videovorschauen erzeugt.\n";
            return 0;
        });
        $x->command('auswahl:selftest', 'Bildauswahl: Link, PIN, Regeln (Paket, Frist, Status), Auswahl und Notizen', fn(array $args): int => Klxm\Auswahl\SelfTest::run());
    },
];
