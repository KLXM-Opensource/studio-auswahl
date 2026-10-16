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

        echo "\n  " . $ok . ' Prüfungen bestanden, ' . count($fails) . " fehlgeschlagen\n";
        return $fails ? 1 : 0;
    }
}
