<?php
/**
 * Inline SVG glyphs for #mediaplayer transport / user tools.
 * Fill uses currentColor so brand --primary-color applies via CSS.
 */
if (!function_exists('bandpromo_player_icon_svg')) {
    function bandpromo_player_icon_svg(string $name): string
    {
        $icons = [
            'play' => '<path d="M8 5v14l11-7z"/>',
            'pause' => '<path d="M6 5h4v14H6zm8 0h4v14h-4z"/>',
            'prev' => '<path d="M6 6h2v12H6zm3.5 6 8.5 6V6z"/>',
            'next' => '<path d="M16 6h2v12h-2zM6 18l8.5-6L6 6z"/>',
            'repeat' => '<path d="M7 7h10v3l4-4-4-4v3H5v6h2V7zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2v4z"/>',
            // Larger centred “1” so Repeat one stays readable at control size.
            'repeat-one' => '<path d="M7 6h10v2.5l3.25-3.25L17 2V4.5H5V10h2V6zm10 12H7v-2.5l-3.25 3.25L7 22v-2.5h12V14h-2v4z"/><path d="M10.6 8.4h2.15v7.2H10.9v-5.55H9.55V8.95c.4-.22.78-.4 1.35-.7.25-.12.5-.25.7-.4z"/>',
            'cast' => '<path d="M3 5h18v14h-7v-2h5V7H5v2H3V5zm0 12a4 4 0 0 1 4 4H5a2 2 0 0 0-2-2v-2zm0-4a8 8 0 0 1 8 8h-2a6 6 0 0 0-6-6v-2zm0-4a12 12 0 0 1 12 12h-2A10 10 0 0 0 3 13V9z"/>',
            'user' => '<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5z"/>',
        ];
        $path = $icons[$name] ?? '';
        if ($path === '') {
            return '';
        }
        return '<svg class="player-icon player-icon--' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . '" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">'
            . $path . '</svg>';
    }
}
