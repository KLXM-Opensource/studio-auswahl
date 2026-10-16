<?php
/** Symbole der Bildauswahl als SVG (currentColor), z. B. $i('heart'). Gibt eine Closure zurück. */
return static function (string $name, string $class = ''): string {
    $p = [
        'heart' => '<path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 8 3.4 4.5 6.9 4.5c2 0 3.6 1.1 4.6 2.6.9-1.5 2.6-2.6 4.6-2.6 3.5 0 5.5 3.5 4.2 6.8-1.8 4.6-9.3 9.2-9.3 9.2z"/>',
        'note' => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H10l-4.5 4v-4h0A1.5 1.5 0 0 1 4 14.5z"/>',
        'download' => '<path d="M12 4v11m0 0-4.5-4.5M12 15l4.5-4.5M5 19.5h14"/>',
        'play' => '<path d="M8 5.5v13l10.5-6.5z"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'prev' => '<path d="M15 5 8 12l7 7"/>',
        'next' => '<path d="m9 5 7 7-7 7"/>',
        'send' => '<path d="M4 12 20 4l-5 16-3-7z"/>',
        'lock' => '<path d="M7 10.5V8a5 5 0 0 1 10 0v2.5M5.5 10.5h13v9h-13z"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    ][$name] ?? '';
    return '<svg class="aw-i' . ($class !== '' ? ' ' . $class : '') . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $p . '</svg>';
};
