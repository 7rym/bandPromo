<?php
declare(strict_types=1);

require_once __DIR__ . '/page-text-sanitize.php';

const BANDPROMO_PAGE_SCHEMA_VERSION = 2;

const BANDPROMO_PAGE_PICTURE_LAYOUTS = [
    'centered',
    'full',
    'left-wrap',
    'right-wrap',
    'left-under',
    'right-under',
];

const BANDPROMO_PAGE_PICTURE_WIDTH_MIN = 1;

const BANDPROMO_PAGE_PICTURE_WIDTH_MAX = 6;

const BANDPROMO_PAGE_PICTURE_FLOWS = [
    'row',
    'row-end',
    'wrap-left',
    'wrap-right',
    'beside-left',
    'beside-right',
];

function bandpromo_page_operator_picture_styles(): array {
    $widthOptions = [];
    for ($value = BANDPROMO_PAGE_PICTURE_WIDTH_MIN; $value <= BANDPROMO_PAGE_PICTURE_WIDTH_MAX; $value++) {
        $widthOptions[] = ['value' => $value, 'label' => (string) $value];
    }

    return [
        'width_min' => BANDPROMO_PAGE_PICTURE_WIDTH_MIN,
        'width_max' => BANDPROMO_PAGE_PICTURE_WIDTH_MAX,
        'width_options' => $widthOptions,
        'flows' => [
            ['value' => 'row', 'label' => 'In row'],
            ['value' => 'row-end', 'label' => 'End of row'],
            ['value' => 'wrap-left', 'label' => 'Wrap left'],
            ['value' => 'wrap-right', 'label' => 'Wrap right'],
            ['value' => 'beside-left', 'label' => 'Beside left'],
            ['value' => 'beside-right', 'label' => 'Beside right'],
        ],
    ];
}

function bandpromo_page_picture_style_from_layout(string $layout): array {
    return match ($layout) {
        'full' => ['width_num' => 1, 'width_den' => 1, 'flow' => 'row'],
        'centered' => ['width_num' => 3, 'width_den' => 4, 'flow' => 'row'],
        'left-wrap' => ['width_num' => 1, 'width_den' => 2, 'flow' => 'wrap-left'],
        'right-wrap' => ['width_num' => 1, 'width_den' => 2, 'flow' => 'wrap-right'],
        'left-under' => ['width_num' => 1, 'width_den' => 2, 'flow' => 'row'],
        'right-under' => ['width_num' => 1, 'width_den' => 2, 'flow' => 'row-end'],
        default => ['width_num' => 3, 'width_den' => 4, 'flow' => 'row'],
    };
}

function bandpromo_page_normalize_picture_width_part(mixed $value): int {
    $part = (int) $value;
    if ($part < BANDPROMO_PAGE_PICTURE_WIDTH_MIN) {
        return BANDPROMO_PAGE_PICTURE_WIDTH_MIN;
    }
    if ($part > BANDPROMO_PAGE_PICTURE_WIDTH_MAX) {
        return BANDPROMO_PAGE_PICTURE_WIDTH_MAX;
    }

    return $part;
}

function bandpromo_page_normalize_picture_width_fraction(int $num, int $den): array {
    $num = bandpromo_page_normalize_picture_width_part($num);
    $den = bandpromo_page_normalize_picture_width_part($den);
    if ($num > $den) {
        $den = $num;
    }

    return ['width_num' => $num, 'width_den' => $den];
}

function bandpromo_page_normalize_picture_flow(string $value): string {
    $flow = strtolower(trim($value));
    if (!in_array($flow, BANDPROMO_PAGE_PICTURE_FLOWS, true)) {
        return 'row';
    }

    return $flow;
}

function bandpromo_page_legacy_size_to_fraction(mixed $value): array {
    $size = (int) $value;

    return match ($size) {
        25 => ['width_num' => 1, 'width_den' => 4],
        33 => ['width_num' => 1, 'width_den' => 3],
        50 => ['width_num' => 1, 'width_den' => 2],
        75 => ['width_num' => 3, 'width_den' => 4],
        100 => ['width_num' => 1, 'width_den' => 1],
        default => ['width_num' => 1, 'width_den' => 2],
    };
}

function bandpromo_page_legacy_style_to_flow(string $align, string $text): string {
    $align = bandpromo_page_normalize_picture_align_legacy($align);
    $text = bandpromo_page_normalize_picture_text_mode_legacy($text);

    if ($text === 'wrap') {
        return $align === 'right' ? 'wrap-right' : 'wrap-left';
    }

    if ($text === 'beside') {
        return $align === 'right' ? 'beside-right' : 'beside-left';
    }

    if ($align === 'right') {
        return 'row-end';
    }

    return 'row';
}

function bandpromo_page_normalize_picture_align_legacy(string $value): string {
    $align = strtolower(trim($value));
    if (!in_array($align, ['left', 'center', 'right'], true)) {
        return 'center';
    }

    return $align;
}

function bandpromo_page_normalize_picture_text_mode_legacy(string $value): string {
    $mode = strtolower(trim($value));
    if (!in_array($mode, ['under', 'beside', 'wrap'], true)) {
        return 'under';
    }

    return $mode;
}

function bandpromo_page_resolve_picture_style(array $block): array {
    if (isset($block['width_num']) || isset($block['width_den']) || isset($block['flow'])) {
        $fraction = bandpromo_page_normalize_picture_width_fraction(
            bandpromo_page_normalize_picture_width_part($block['width_num'] ?? 1),
            bandpromo_page_normalize_picture_width_part($block['width_den'] ?? 1)
        );

        return [
            'width_num' => $fraction['width_num'],
            'width_den' => $fraction['width_den'],
            'flow' => bandpromo_page_normalize_picture_flow((string) ($block['flow'] ?? 'row')),
        ];
    }

    if (isset($block['size']) || isset($block['align']) || isset($block['text'])) {
        $fraction = bandpromo_page_legacy_size_to_fraction($block['size'] ?? 50);

        return [
            'width_num' => $fraction['width_num'],
            'width_den' => $fraction['width_den'],
            'flow' => bandpromo_page_legacy_style_to_flow(
                (string) ($block['align'] ?? 'center'),
                (string) ($block['text'] ?? 'under')
            ),
        ];
    }

    $layout = bandpromo_page_normalize_picture_layout((string) ($block['layout'] ?? 'centered'));

    return bandpromo_page_picture_style_from_layout($layout);
}

function bandpromo_page_picture_style_classes(array $style): string {
    $resolved = bandpromo_page_resolve_picture_style($style);

    return 'page-picture--flow-' . $resolved['flow'];
}

function bandpromo_page_picture_style_inline(array $style): string {
    $resolved = bandpromo_page_resolve_picture_style($style);

    return '--pw-num:' . $resolved['width_num'] . ';--pw-den:' . $resolved['width_den'];
}

function bandpromo_page_operator_image_layouts(): array {
    return bandpromo_page_operator_picture_styles();
}

const BANDPROMO_PAGE_IMAGE_PRESETS = [
    'feature',
    'wide',
    'float-left',
    'float-right',
];

const BANDPROMO_PAGE_GALLERY_PRESETS = [
    'grid',
    'list',
    'carousel',
    'parallax',
];

const BANDPROMO_PAGE_BLOCK_TYPES = [
    'richtext',
    'picture',
    'picture_richtext',
    'list',
    'gallery',
    'heading',
    'paragraph',
    'quote',
    'image',
    'divider',
    'callout',
];

function bandpromo_page_default_titles(): array {
    return [
        'bio' => 'Band Bio',
        'faq' => 'FAQ / Info',
    ];
}

function bandpromo_page_normalize_id(string $pageId): string {
    return strtolower(trim($pageId));
}

function bandpromo_page_normalize_picture_layout(string $layout): string {
    $layout = strtolower(trim($layout));
    if (!in_array($layout, BANDPROMO_PAGE_PICTURE_LAYOUTS, true)) {
        return 'centered';
    }

    return $layout;
}

function bandpromo_page_legacy_preset_to_layout(string $preset): string {
    $preset = strtolower(trim($preset));

    return match ($preset) {
        'wide', 'banner' => 'full',
        'float-left' => 'left-wrap',
        'float-right' => 'right-wrap',
        default => 'centered',
    };
}

function bandpromo_page_new_document(string $pageId, string $title = ''): array {
    $pageId = bandpromo_page_normalize_id($pageId);
    $titles = bandpromo_page_default_titles();
    $resolvedTitle = trim($title);
    if ($resolvedTitle === '') {
        $resolvedTitle = $titles[$pageId] ?? ucfirst($pageId);
    }

    return [
        'version' => BANDPROMO_PAGE_SCHEMA_VERSION,
        'id' => $pageId,
        'title' => $resolvedTitle,
        'release_id' => $pageId === 'bio' ? 'bandpromo-demo' : '',
        'short_description' => '',
        'description' => '',
        'poster_asset_id' => '',
        'updated_at' => gmdate('c'),
        'blocks' => [],
    ];
}

function bandpromo_page_normalize_text(string $value, int $maxLength = 12000): string {
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    if ($value === '') {
        return '';
    }

    if (bandpromo_page_text_contains_markup($value)) {
        $value = bandpromo_page_sanitize_rich_text($value);
    }

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return (string) mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function bandpromo_page_normalize_url(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if ($value[0] === '/' || $value[0] === '#') {
        return $value;
    }

    if (preg_match('/^(https?|mailto):/i', $value) === 1) {
        return $value;
    }

    return '';
}

function bandpromo_page_is_allowed_image_src(string $src): bool {
    $allowedPrefixes = [
        '/media/img/optimal/',
        '/media/photo/optimal/',
        '/media/visual/delivery/',
        '/media/special/',
    ];

    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($src, $prefix)) {
            return true;
        }
    }

    // Bare asset id or basename that resolves later.
    require_once __DIR__ . '/asset-registry.php';
    $ref = basename(trim($src));
    if ($ref !== '' && bandpromo_asset_is_asset_id($ref)) {
        return true;
    }

    return false;
}

/**
 * Resolve a page picture block to a public delivery URL (asset_id preferred).
 */
function bandpromo_page_resolve_picture_src(string $root, array $block): string
{
    require_once __DIR__ . '/media-delivery-helpers.php';

    $assetId = trim((string) ($block['asset_id'] ?? ''));
    if ($assetId !== '') {
        $url = bandpromo_visual_resolve_url($root, $assetId, 'card');
        if ($url !== '') {
            return $url;
        }
    }

    $src = trim((string) ($block['src'] ?? ''));
    if ($src === '') {
        return '';
    }

    if (str_starts_with($src, '/media/visual/delivery/') || str_starts_with($src, 'http')) {
        return $src;
    }

    $resolved = bandpromo_visual_resolve_url($root, basename($src), 'card');
    return $resolved !== '' ? $resolved : $src;
}

function bandpromo_page_plain_fragment_to_html(array $block): string {
    $type = (string) ($block['type'] ?? '');

    if ($type === 'heading') {
        $level = (int) ($block['level'] ?? 2);
        if (!in_array($level, [2, 3, 4], true)) {
            $level = 2;
        }
        $text = bandpromo_page_normalize_text((string) ($block['text'] ?? ''), 500);
        if ($text === '') {
            return '';
        }

        return '<h' . $level . '>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h' . $level . '>';
    }

    if ($type === 'paragraph') {
        $text = bandpromo_page_normalize_text((string) ($block['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        if (bandpromo_page_text_contains_markup($text)) {
            return bandpromo_page_sanitize_document_html($text);
        }

        return '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>';
    }

    if ($type === 'quote') {
        $text = bandpromo_page_normalize_text((string) ($block['text'] ?? ''), 2000);
        if ($text === '') {
            return '';
        }

        return '<blockquote><p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></blockquote>';
    }

    if ($type === 'callout') {
        $text = bandpromo_page_normalize_text((string) ($block['text'] ?? ''), 2000);
        if ($text === '') {
            return '';
        }

        return '<p><em>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</em></p>';
    }

    return '';
}

function bandpromo_page_is_picture_family(string $type): bool {
    return in_array($type, ['picture', 'picture_richtext'], true);
}

function bandpromo_page_is_modern_block(array $block): bool {
    $type = (string) ($block['type'] ?? '');

    return in_array($type, ['richtext', 'picture', 'picture_richtext', 'list', 'gallery'], true);
}

function bandpromo_page_is_text_fragment_block(array $block): bool {
    $type = (string) ($block['type'] ?? '');

    return in_array($type, ['heading', 'paragraph', 'quote', 'callout'], true);
}

function bandpromo_page_migrate_blocks(array $blocks): array {
    if ($blocks === []) {
        return [];
    }

    $hasLegacy = false;
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $type = (string) ($block['type'] ?? '');
        if ($type === 'list') {
            continue;
        }
        if (!bandpromo_page_is_modern_block($block)) {
            $hasLegacy = true;
            break;
        }
    }

    if (!$hasLegacy) {
        return $blocks;
    }

    $migrated = [];
    $count = count($blocks);
    $index = 0;

    while ($index < $count) {
        $block = $blocks[$index];
        if (!is_array($block)) {
            $index++;
            continue;
        }

        $type = (string) ($block['type'] ?? '');

        if ($type === 'list') {
            $migrated[] = $block;
            $index++;
            continue;
        }

        if ($type === 'divider') {
            $index++;
            continue;
        }

        if ($type === 'image' || $type === 'picture') {
            $layout = $type === 'picture'
                ? bandpromo_page_normalize_picture_layout((string) ($block['layout'] ?? 'centered'))
                : bandpromo_page_legacy_preset_to_layout((string) ($block['preset'] ?? 'feature'));

            $bodyParts = [];
            if ($type === 'picture' && trim((string) ($block['body'] ?? '')) !== '') {
                $bodyParts[] = (string) $block['body'];
            }

            $index++;
            while ($index < $count) {
                $next = $blocks[$index];
                if (!is_array($next) || !bandpromo_page_is_text_fragment_block($next)) {
                    break;
                }
                $fragment = bandpromo_page_plain_fragment_to_html($next);
                if ($fragment !== '') {
                    $bodyParts[] = $fragment;
                }
                $index++;
            }

            $bodyHtml = implode('', $bodyParts);
            $migrated[] = [
                'type' => $bodyHtml !== '' ? 'picture_richtext' : 'picture',
                'src' => (string) ($block['src'] ?? ''),
                'alt' => (string) ($block['alt'] ?? 'Picture'),
                'layout' => $layout,
                'body' => $bodyHtml,
                'caption' => (string) ($block['caption'] ?? ''),
            ];
            continue;
        }

        if (bandpromo_page_is_text_fragment_block($block)) {
            $parts = [];
            while ($index < $count) {
                $next = $blocks[$index];
                if (!is_array($next) || !bandpromo_page_is_text_fragment_block($next)) {
                    break;
                }
                $fragment = bandpromo_page_plain_fragment_to_html($next);
                if ($fragment !== '') {
                    $parts[] = $fragment;
                }
                $index++;
            }

            if ($parts !== []) {
                $migrated[] = [
                    'type' => 'richtext',
                    'html' => implode('', $parts),
                ];
            }
            continue;
        }

        $index++;
    }

    return $migrated;
}

function bandpromo_page_normalize_block(array $block): ?array {
    $type = isset($block['type']) ? strtolower(trim((string) $block['type'])) : '';
    if (!in_array($type, BANDPROMO_PAGE_BLOCK_TYPES, true)) {
        return null;
    }

    if ($type === 'richtext') {
        $html = bandpromo_page_sanitize_document_html((string) ($block['html'] ?? ''));
        if ($html === '') {
            return null;
        }

        return [
            'type' => 'richtext',
            'html' => $html,
        ];
    }

    if ($type === 'picture' || $type === 'picture_richtext') {
        $assetId = trim((string) ($block['asset_id'] ?? ''));
        require_once __DIR__ . '/asset-registry.php';
        if ($assetId !== '' && !bandpromo_asset_is_asset_id($assetId)) {
            $assetId = '';
        }

        $src = bandpromo_page_normalize_url((string) ($block['src'] ?? ''));
        if ($src === '' && $assetId !== '') {
            $src = $assetId;
        }
        if (!bandpromo_page_is_allowed_image_src($src) && $assetId === '') {
            return null;
        }
        if ($src === '' && $assetId === '') {
            return null;
        }

        $style = bandpromo_page_resolve_picture_style($block);
        $alt = bandpromo_page_normalize_text((string) ($block['alt'] ?? ''), 240);
        if ($alt === '') {
            $alt = 'Picture';
        }

        $body = bandpromo_page_sanitize_document_html((string) ($block['body'] ?? ''));
        $resolvedType = $type === 'picture_richtext' || $body !== '' ? 'picture_richtext' : 'picture';

        $normalized = [
            'type' => $resolvedType,
            'src' => $src !== '' ? $src : $assetId,
            'alt' => $alt,
            'width_num' => $style['width_num'],
            'width_den' => $style['width_den'],
            'flow' => $style['flow'],
        ];
        if ($assetId !== '') {
            $normalized['asset_id'] = $assetId;
        }

        if ($resolvedType === 'picture_richtext') {
            $normalized['body'] = $body;
        } else {
            $caption = bandpromo_page_normalize_text((string) ($block['caption'] ?? ''), 500);
            if ($caption !== '') {
                $normalized['caption'] = $caption;
            }
        }

        return $normalized;
    }

    if ($type === 'list') {
        $style = strtolower(trim((string) ($block['style'] ?? 'unordered')));
        if (!in_array($style, ['unordered', 'ordered'], true)) {
            $style = 'unordered';
        }

        $items = [];
        if (isset($block['items']) && is_array($block['items'])) {
            foreach ($block['items'] as $item) {
                $normalized = bandpromo_page_normalize_text((string) $item, 1000);
                if ($normalized !== '') {
                    $items[] = $normalized;
                }
            }
        }

        if ($items === []) {
            return null;
        }

        return [
            'type' => 'list',
            'style' => $style,
            'items' => $items,
        ];
    }

    if ($type === 'gallery') {
        require_once __DIR__ . '/gallery-storage.php';
        $galleryId = bandpromo_gallery_resolve_id((string) ($block['gallery_id'] ?? BANDPROMO_GALLERY_DEMO_ID));

        $preset = strtolower(trim((string) ($block['preset'] ?? 'grid')));
        if (!in_array($preset, BANDPROMO_PAGE_GALLERY_PRESETS, true)) {
            $preset = 'grid';
        }

        return [
            'type' => 'gallery',
            'gallery_id' => $galleryId,
            'preset' => $preset,
        ];
    }

    return null;
}

function bandpromo_page_normalize_document(array $input, string $expectedId): array {
    $pageId = bandpromo_page_normalize_id((string) ($input['id'] ?? $expectedId));
    if ($pageId !== bandpromo_page_normalize_id($expectedId)) {
        throw new InvalidArgumentException('Page id mismatch.');
    }

    $document = bandpromo_page_new_document($pageId, (string) ($input['title'] ?? ''));
    $rawBlocks = isset($input['blocks']) && is_array($input['blocks']) ? $input['blocks'] : [];
    $migratedBlocks = bandpromo_page_migrate_blocks($rawBlocks);
    $blocks = [];

    foreach ($migratedBlocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $normalized = bandpromo_page_normalize_block($block);
        if ($normalized !== null) {
            $blocks[] = $normalized;
        }
    }

    $document['blocks'] = $blocks;
    $releaseId = trim((string) ($input['release_id'] ?? $document['release_id'] ?? ''));
    if ($releaseId !== '' && !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $releaseId)) {
        $releaseId = '';
    }
    if ($releaseId === '' && $pageId === 'bio') {
        $releaseId = 'bandpromo-demo';
    }
    $document['release_id'] = $releaseId;
    if (array_key_exists('short_description', $input)) {
        $document['short_description'] = bandpromo_page_normalize_text((string) $input['short_description'], 300);
    }
    if (array_key_exists('description', $input)) {
        $document['description'] = bandpromo_page_normalize_text((string) $input['description'], 4000);
    }
    if (array_key_exists('poster_asset_id', $input)) {
        $document['poster_asset_id'] = trim((string) $input['poster_asset_id']);
    }
    $document['updated_at'] = gmdate('c');

    return $document;
}
