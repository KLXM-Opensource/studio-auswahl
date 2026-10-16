<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Bildauswahl. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\Auswahl;

use Core\Media;

/**
 * Vorschauen für Kundinnen und Kunden – nie das Original, solange das Studio nicht freigegeben hat:
 *   - Bilder: höchstens SIZES['l'] Pixel an der langen Kante, WebP, Wasserzeichen ins Bild gerechnet (GD, Lato aus dem Kern)
 *   - Videos: 720p-MP4 mit Wasserzeichen (ffmpeg), erzeugt nach der Antwort einer Verwaltungsseite (Extension::afterAdminResponse)
 * Freigegeben (Schalter allow_download): Bildschirmfassungen ohne Wasserzeichen, Originale über …/datei/{id}.
 * Zwischenspeicher außerhalb von public/: storage/auswahl/{website}/{shooting}/ – Name enthält Größe, Wasserzeichen und Stand.
 */
final class Preview
{
    public const SIZES = ['s' => 720, 'l' => 1800];

    public static function dir(array $s): string
    {
        return ROOT . '/storage/auswahl/' . preg_replace('~[^a-z0-9_-]~i', '_', site()->key) . '/' . (int) $s['id'];
    }

    /** Wasserzeichen-Text (Einstellungen; {site} = Name der Website) – leer bei Freigabe */
    public static function mark(array $s): string
    {
        if ((int) $s['allow_download']) return '';
        $t = trim((string) app()->settings->get('auswahl.watermark', ''));
        return strtr($t !== '' ? $t : '© {site} · ' . lt('Vorschau'), ['{site}' => site_name()]);
    }

    private static function key(array $s, array $m, string $what): string
    {
        return (int) $m['id'] . '-' . $what . '-' . substr(sha1(self::mark($s) . '|' . ($m['updated_at'] ?? '') . '|' . ($m['file'] ?? '')), 0, 10);
    }

    /** Datei der Bildvorschau (erzeugt bei Bedarf) oder null */
    public static function image(array $s, array $m, string $size): ?string
    {
        $w = self::SIZES[$size] ?? self::SIZES['s'];
        $src = str_starts_with((string) $m['mime'], 'video/') ? Media::posterFor($m) : $m;
        if (!$src || !str_starts_with((string) $src['mime'], 'image/')) return null;
        $file = self::dir($s) . '/' . self::key($s, $m, $size) . '.webp';
        if (is_file($file)) return $file;
        // Quelle: kleinste vorhandene Größe ≥ Zielbreite (schon gedreht und verkleinert), sonst das Original
        $in = Media::localFile($src, $w);
        if (!is_file($in)) $in = Media::localFile($src);
        if (!is_file($in)) return null;
        $im = self::load($in);
        if (!$im) return null;
        $im = self::fit($im, $w);
        if (($t = self::mark($s)) !== '') self::watermark($im, $t);
        if (!is_dir(dirname($file))) @mkdir(dirname($file), 0770, true);
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        imagewebp($im, $tmp, 80);
        @rename($tmp, $file);
        return is_file($file) ? $file : null;
    }

    private static function load(string $f): ?\GdImage
    {
        $type = (string) ((new \finfo(FILEINFO_MIME_TYPE))->file($f) ?: '');
        $im = match ($type) {
            'image/webp' => @imagecreatefromwebp($f),
            'image/jpeg' => @imagecreatefromjpeg($f),
            'image/png' => @imagecreatefrompng($f),
            'image/gif' => @imagecreatefromgif($f),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($f) : false,
            default => false,
        };
        if (!$im) return null;
        if (!imageistruecolor($im)) imagepalettetotruecolor($im);
        return $im;
    }

    /** Lange Kante auf höchstens $w Pixel */
    private static function fit(\GdImage $im, int $w): \GdImage
    {
        $ow = imagesx($im);
        $oh = imagesy($im);
        $k = min(1, $w / max($ow, $oh));
        if ($k >= 1) return $im;
        $nw = max(1, (int) round($ow * $k));
        $nh = max(1, (int) round($oh * $k));
        $out = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
        return $out;
    }

    private static function font(): ?string
    {
        foreach ([ROOT . '/resources/fonts/pdf/lato-700-normal.ttf', ROOT . '/resources/fonts/pdf/lato-400-normal.ttf'] as $f) if (is_file($f)) return $f;
        return null;
    }

    /** Wasserzeichen: Text schräg über das ganze Bild verteilt (hell mit dunkler Kante – auf hellen und dunklen Bildern sichtbar) */
    public static function watermark(\GdImage $im, string $text, ?int $ref = null): void
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $font = self::font();
        imagealphablending($im, true);
        $light = imagecolorallocatealpha($im, 255, 255, 255, 82);
        $dark = imagecolorallocatealpha($im, 0, 0, 0, 100);
        if (!$font || !function_exists('imagettftext')) {
            for ($y = 20; $y < $h; $y += 90) for ($x = ($y / 90 % 2) * 120; $x < $w; $x += 320) imagestring($im, 5, (int) $x, (int) $y, $text, $light);
            return;
        }
        $size = max(14, (int) round(($ref ?? min($w, $h)) / 22));
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = abs($box[2] - $box[0]) + $size * 3;
        $th = $size * 5;
        $angle = 28;
        for ($row = -2, $y = -$h * .2; $y < $h * 1.3; $y += $th, $row++) {
            for ($x = -$w * .3 + ($row % 2) * $tw / 2; $x < $w * 1.2; $x += $tw) {
                imagettftext($im, $size, $angle, (int) $x + 2, (int) $y + 2, $dark, $font, $text);
                imagettftext($im, $size, $angle, (int) $x, (int) $y, $light, $font, $text);
            }
        }
    }

    // ------------------------------------------------------------------ Videos

    /** Datei der Videovorschau oder null (noch nicht erzeugt bzw. ohne ffmpeg) */
    public static function video(array $s, array $m): ?string
    {
        $f = self::dir($s) . '/' . self::key($s, $m, 'v') . '.mp4';
        return is_file($f) ? $f : null;
    }

    /** Videovorschau erzeugen (720p, Wasserzeichen als eingerechneter Text, Ton bleibt). true = vorhanden */
    public static function makeVideo(array $s, array $m): bool
    {
        if (self::video($s, $m)) return true;
        if (!\Core\Ffmpeg::available()) return false;
        $in = Media::localFile($m);
        if (!is_file($in)) return false;
        $out = self::dir($s) . '/' . self::key($s, $m, 'v') . '.mp4';
        if (!is_dir(dirname($out))) @mkdir(dirname($out), 0770, true);
        $tmp = $out . '.tmp.mp4';
        // Wasserzeichen als transparentes PNG (GD, gleiche Schrift wie bei Bildern) per overlay – drawtext fehlt vielen ffmpeg-Builds
        $wm = null;
        if (($t = self::mark($s)) !== '') {
            $wm = $out . '.wm.png';
            $png = imagecreatetruecolor(1280, 2276);
            imagesavealpha($png, true);
            imagealphablending($png, false);
            imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
            imagealphablending($png, true);
            self::watermark($png, $t, 1280);
            imagepng($png, $wm);
        }
        $vf = $wm ? "[0:v]scale='min(1280,iw)':-2[v];[v][1:v]overlay=0:0:shortest=1,format=yuv420p" : "scale='min(1280,iw)':-2,format=yuv420p";
        $cmd = escapeshellarg(\Core\Ffmpeg::bin('ffmpeg')) . ' -y -loglevel error -i ' . escapeshellarg($in)
            . ($wm ? ' -loop 1 -i ' . escapeshellarg($wm) . ' -filter_complex ' . escapeshellarg($vf) . ' -map 0:a? ' : ' -vf ' . escapeshellarg($vf) . ' ')
            . '-c:v libx264 -crf 28 -preset veryfast -c:a aac -b:a 96k -movflags +faststart ' . escapeshellarg($tmp) . ' 2>&1';
        exec($cmd, $o, $rc);
        if ($wm) @unlink($wm);
        if ($rc !== 0 || !is_file($tmp) || filesize($tmp) < 1000) { @unlink($tmp); error_log('[auswahl] Videovorschau: ' . implode(' ', array_slice($o, 0, 3))); return false; }
        return @rename($tmp, $out);
    }

    /** Merker „Videovorschauen ausstehend“ (billig prüfbar bei jedem Verwaltungsaufruf) */
    public static function flag(bool $set = true): string
    {
        $f = ROOT . '/storage/auswahl/' . preg_replace('~[^a-z0-9_-]~i', '_', site()->key) . '/videos-pending';
        if ($set) { @mkdir(dirname($f), 0770, true); @touch($f); }
        return $f;
    }

    /** Ausstehende Videovorschauen erzeugen (höchstens $max); Merker weg, wenn keine mehr fehlen. → Anzahl erzeugt */
    public static function work(int $max = 1): int
    {
        $n = 0;
        foreach (self::pendingVideos($max) as [$s, $m]) if (self::makeVideo($s, $m)) $n++;
        if (!self::pendingVideos(1) || !\Core\Ffmpeg::available()) @unlink(self::flag(false));
        return $n;
    }

    /** Fehlende Videovorschauen dieser Website suchen → Liste [Shooting, Datei] (höchstens $max) */
    public static function pendingVideos(int $max = 1): array
    {
        $out = [];
        foreach (app()->db->fetchAll("SELECT * FROM auswahl_shoots WHERE status <> 'closed' ORDER BY id DESC") as $s) {
            foreach (Repo::items($s) as $m) {
                if (str_starts_with((string) $m['mime'], 'video/') && !self::video($s, $m)) $out[] = [$s, $m];
                if (count($out) >= $max) return $out;
            }
        }
        return $out;
    }

    /** Zwischenspeicher eines Shootings bzw. einer Datei löschen */
    public static function forget(array $s, ?int $mediaId = null): void
    {
        $dir = self::dir($s);
        foreach (glob($dir . '/' . ($mediaId !== null ? $mediaId . '-*' : '*')) ?: [] as $f) @unlink($f);
        if ($mediaId === null) @rmdir($dir);
    }
}
