<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Auswahl;

/**
 * php bin/console auswahl:selftest --site=… – Link, PIN, Frist, Status, Auswahl und Notizen, Zugehörigkeit der Dateien.
 * Legt eine Sammlung mit zwei erzeugten Bildern an und läuft in einer Transaktion, die zurückgerollt wird (Dateien werden entfernt).
 */
final class SelfTest
{
    public static function run(): int
    {
        if (!\Core\Extensions::isActive(Auswahl::NAME)) {
            echo 'Erweiterung „auswahl“ ist auf Website „' . site()->key . "“ nicht aktiv – nichts zu tun (auswahl:selftest).\n";
            return 0;
        }
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, '', true);

        echo "Regeln\n";
        $eq('PIN leer erlaubt', Auswahl::validPin(''), true);
        $eq('PIN 4 Ziffern', Auswahl::validPin('4711'), true);
        $eq('PIN zu kurz', Auswahl::validPin('12'), false);
        $eq('PIN mit Sonderzeichen', Auswahl::validPin('12 34'), false);
        $base = ['status' => 'open', 'deadline' => ''];
        $eq('offen ohne Frist: auswählbar', Auswahl::editable($base), true);
        $eq('Frist gestern: abgelaufen', Auswahl::expired(['deadline' => date('Y-m-d', strtotime('-1 day'))] + $base), true);
        $eq('Frist heute: noch offen', Auswahl::expired(['deadline' => date('Y-m-d')] + $base), false);
        $eq('abgeschickt: nicht mehr auswählbar', Auswahl::editable(['status' => 'submitted'] + $base), false);
        $eq('abgeschlossen: nicht aufrufbar', Auswahl::viewable(['status' => 'closed'] + $base), false);
        $eq('Link-Format: zu kurz', Repo::byToken('abc'), null);
        $eq('Link-Format: fremde Zeichen', Repo::byToken(str_repeat('a', 30) . '/..'), null);

        $db = app()->db;
        $files = [];
        $db->pdo->beginTransaction();
        try {
            echo "Shooting\n";
            $cid = \Core\Media::createCollection('Selbsttest Bildauswahl');
            $ids = [];
            foreach ([[600, 400], [400, 600]] as $k => [$w, $h]) {
                $f = tempnam(sys_get_temp_dir(), 'aw') . '.jpg';
                $im = imagecreatetruecolor($w, $h);
                imagefill($im, 0, 0, imagecolorallocate($im, 120 + $k * 40, 120, 120));
                imagejpeg($im, $f, 80);
                [$m] = \Core\Media::import($f, 'DSC_00' . ($k + 1) . '.jpg', 'Selbsttest', ['collection' => $cid]);
                @unlink($f);
                if ($m) { $ids[] = (int) $m['id']; $files[] = $m; }
            }
            $eq('zwei Testbilder', count($ids), 2);
            $other = \Core\Media::createCollection('Selbsttest fremd');
            $id = Repo::create(['title' => 'Test', 'collection_id' => $cid, 'max_picks' => 1, 'pin_hash' => password_hash('4711', PASSWORD_DEFAULT)]);
            $s = Repo::find($id);
            $eq('Link 32 Zeichen, eindeutig', strlen((string) $s['token']) === 32 && Repo::byToken((string) $s['token'])['id'] == $id, true);
            $eq('falscher Link', Repo::byToken(strrev((string) $s['token'])) === null, true);
            $eq('Dateien in Reihenfolge der Sammlung', array_map('intval', array_column(Repo::items($s), 'id')), $ids);
            $eq('Datei gehört dazu', Repo::hasItem($s, $ids[0]), true);
            $eq('fremde Datei gehört nicht dazu', Repo::hasItem(['collection_id' => $other] + $s, $ids[0]), false);
            $eq('PIN gespeichert als Hash', password_verify('4711', (string) $s['pin_hash']) && !str_contains((string) $s['pin_hash'], '4711'), true);

            echo "Auswahl\n";
            Repo::setPick($id, $ids[0], true);
            Repo::setPick($id, $ids[1], null, 'bitte heller');
            $eq('eine ausgewählt', Repo::pickedCount($id), 1);
            $p = Repo::picks($id);
            $eq('Notiz ohne Auswahl', [$p[$ids[1]]['picked'], $p[$ids[1]]['note']], [false, 'bitte heller']);
            Repo::setPick($id, $ids[0], null, 'Favorit');
            $eq('Notiz ändert die Auswahl nicht', Repo::picks($id)[$ids[0]]['picked'], true);
            $sel = Repo::selection($s);
            $eq('Auswahl + Notizen', count($sel), 2);
            $eq('Dateinamen ohne Endung', Repo::fileNames(array_values(array_filter($sel, fn($m) => $m['_picked']))), ['DSC_001']);
            Repo::setPick($id, $ids[0], false);
            $eq('abgewählt', Repo::pickedCount($id), 0);
            $eq('Zähler je Status', Repo::counts()['open'] >= 1, true);
            Repo::update($id, ['token' => Repo::newToken()]);
            $eq('neuer Link: alter ungültig', Repo::byToken((string) $s['token']), null);
            Repo::delete($id);
            $eq('gelöscht samt Auswahl', [Repo::find($id), Repo::picks($id)], [null, []]);
        } finally {
            $db->pdo->rollBack();
            foreach ($files as $m) foreach (glob(dirname(\Core\Media::localFile($m)) . '/' . pathinfo((string) $m['file'], PATHINFO_FILENAME) . '*') ?: [] as $f) @unlink($f);
        }

        echo "Geschützter Ordner (Pool)\n";
        $pool = Auswahl::ensurePool();
        $eq('Pool geschützt, nur diese Website', [\Core\MediaPools::isProtected($pool), \Core\MediaPools::available($pool), \Core\MediaPools::meta($pool)['owner'] ?? ''], [true, true, Auswahl::NAME]);
        $pid = 0;
        $sid = 0;
        try {
            $cid = Repo::inPool(['pool' => $pool], fn() => \Core\Media::createCollection('Selbsttest Bildauswahl'));
            $f = tempnam(sys_get_temp_dir(), 'aw') . '.jpg';
            $im = imagecreatetruecolor(640, 480);
            imagejpeg($im, $f, 80);
            [$m] = Repo::inPool(['pool' => $pool], fn() => \Core\Media::import($f, 'DSC_9001.jpg', 'Selbsttest', ['collection' => $cid]));
            @unlink($f);
            $pid = (int) ($m['id'] ?? 0);
            $sid = Repo::create(['title' => 'Selbsttest Pool', 'pool' => $pool, 'collection_id' => $cid]);
            $s = Repo::find($sid);
            $items = Repo::items($s);
            $eq('Datei im Pool, nicht in der Mediathek der Website', [count($items), (bool) app()->db->fetchValue('SELECT 1 FROM media WHERE original_name = ?', ['DSC_9001.jpg'])], [1, false]);
            $url = \Core\Media::url($items[0]);
            $eq('Adresse geschützt', str_contains($url, \Core\MediaPools::PROTECTED_PATH . '/' . $pool . '/'), true);
            $path = substr($url, strpos($url, '/' . $pool . '/') + strlen($pool) + 2);
            $eq('Original ohne Freigabe nie (auch nicht entsperrt)', (function () use ($s, $pool, $path): bool {
                Auswahl::unlock($s);
                return Auswahl::mediaAllowed(['pool' => $pool, 'path' => $path], new \Core\Http\Request('GET', '/', [], [], [], []));
            })(), false);
            $eq('ohne entsperrtes Shooting kein Zugriff', Auswahl::mediaAllowed(['pool' => $pool, 'path' => $path], new \Core\Http\Request('GET', '/', [], [], [], [])), false);
            $big = Preview::image($s, $items[0], 'l');
            $eq('Vorschau: WebP, höchstens ' . Preview::SIZES['l'] . ' px, nicht das Original', $big && str_ends_with($big, '.webp') && max(array_slice(getimagesize($big) ?: [0, 0], 0, 2)) <= Preview::SIZES['l']
                && realpath($big) !== realpath(\Core\Media::localFile($items[0])), true);
            $eq('Wasserzeichen ohne Freigabe, keins mit Freigabe', [Preview::mark($s) !== '', Preview::mark(['allow_download' => 1] + $s)], [true, '']);
            $eq('fremder Pool: nicht zuständig', Auswahl::mediaAllowed(['pool' => 'mitglieder', 'path' => $path], new \Core\Http\Request('GET', '/', [], [], [], [])), false);
        } finally {
            if ($sid) Repo::delete($sid, true);
            elseif ($pid) Repo::inPool(['pool' => $pool], fn() => \Core\Media::delete($pid, true));
        }
        $eq('Löschen räumt den Pool auf', (int) \Core\MediaPools::db($pool)->fetchValue("SELECT COUNT(*) FROM media WHERE original_name = 'DSC_9001.jpg'"), 0);

        echo "\n  " . $ok . ' Prüfungen bestanden, ' . count($fails) . " fehlgeschlagen\n";
        return $fails ? 1 : 0;
    }
}
