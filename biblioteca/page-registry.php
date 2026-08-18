<?php
declare(strict_types=1);

require_once __DIR__ . '/page-blocks.php';

const BANDPROMO_PAGE_REGISTRY_VERSION = 1;
const BANDPROMO_PAGE_REQUIRED_ID = 'faq';

function bandpromo_page_registry_storage_root(string $root): string {
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pages';
}

function bandpromo_page_registry_path(string $root): string {
    return bandpromo_page_registry_storage_root($root) . DIRECTORY_SEPARATOR . 'registry.json';
}

function bandpromo_page_registry_read_json(string $path): ?array {
    if (!is_file($path)) {
        return null;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

function bandpromo_page_registry_ensure_dir(string $root): void {
    $dir = bandpromo_page_registry_storage_root($root);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create data/pages directory.');
    }
}

function bandpromo_page_registry_template_path(string $root): string {
    return $root . DIRECTORY_SEPARATOR . 'biblioteca' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'pages.registry.template.json';
}

function bandpromo_page_slug_from_title(string $title): string {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'page';
    }

    return substr($slug, 0, 48);
}

function bandpromo_page_normalize_registry_entry(array $entry, int $fallbackOrder = 0): ?array {
    $id = bandpromo_page_normalize_id((string) ($entry['id'] ?? ''));
    if ($id === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $id)) {
        return null;
    }

    $title = bandpromo_page_normalize_text((string) ($entry['title'] ?? ''), 120);
    if ($title === '') {
        $title = ucfirst(str_replace('-', ' ', $id));
    }

    $label = bandpromo_page_normalize_text((string) ($entry['label'] ?? ''), 32);
    if ($label === '') {
        $label = $title;
    }

    $surface = strtolower(trim((string) ($entry['surface'] ?? 'player')));
    if (!in_array($surface, ['player', 'login', 'both'], true)) {
        $surface = 'player';
    }

    $required = $id === BANDPROMO_PAGE_REQUIRED_ID || !empty($entry['required']);
    $system = !empty($entry['system']) || in_array($id, ['bio', 'faq', 'gallery'], true);
    $showInPlayer = array_key_exists('show_in_player', $entry)
        ? (bool) $entry['show_in_player']
        : ($surface !== 'login');

    if ($surface === 'login') {
        $showInPlayer = false;
    }

    return [
        'id' => $id,
        'title' => $title,
        'label' => $label,
        'surface' => $surface,
        'show_in_player' => $showInPlayer,
        'required' => $required,
        'system' => $system,
        'sort_order' => (int) ($entry['sort_order'] ?? $fallbackOrder),
    ];
}

/**
 * Operator-facing page name: registry title (Content → Pages), then document title, then tab label, then id.
 *
 * @param array<string,mixed> $meta
 * @param array<string,mixed> $document
 */
function bandpromo_page_operator_title(array $meta, array $document = []): string
{
    $title = trim((string) ($meta['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }
    $title = trim((string) ($document['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }
    $title = trim((string) ($meta['label'] ?? ''));
    if ($title !== '') {
        return $title;
    }
    $id = trim((string) ($meta['id'] ?? $document['id'] ?? ''));

    return $id !== '' ? $id : 'page';
}

function bandpromo_page_default_registry(): array {
    return [
        'version' => BANDPROMO_PAGE_REGISTRY_VERSION,
        'pages' => [
            [
                'id' => 'bio',
                'title' => 'Band Bio',
                'label' => 'Bio',
                'surface' => 'player',
                'show_in_player' => true,
                'required' => false,
                'system' => true,
                'sort_order' => 10,
            ],
            [
                'id' => 'gallery',
                'title' => 'Gallery',
                'label' => 'Gallery',
                'surface' => 'player',
                'show_in_player' => true,
                'required' => false,
                'system' => true,
                'sort_order' => 15,
            ],
            [
                'id' => 'faq',
                'title' => 'FAQ / Info',
                'label' => 'FAQ',
                'surface' => 'login',
                'show_in_player' => false,
                'required' => true,
                'system' => true,
                'sort_order' => 20,
            ],
        ],
    ];
}

function bandpromo_page_normalize_registry(array $input): array {
    $pages = [];
    $seen = [];
    $order = 0;

    if (isset($input['pages']) && is_array($input['pages'])) {
        foreach ($input['pages'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized = bandpromo_page_normalize_registry_entry($entry, $order * 10);
            if ($normalized === null || isset($seen[$normalized['id']])) {
                continue;
            }
            $seen[$normalized['id']] = true;
            $pages[] = $normalized;
            $order++;
        }
    }

    $hasFaq = false;
    foreach ($pages as $page) {
        if ($page['id'] === BANDPROMO_PAGE_REQUIRED_ID) {
            $hasFaq = true;
            break;
        }
    }

    if (!$hasFaq) {
        throw new InvalidArgumentException('FAQ is a required page and must stay in the page registry.');
    }

    usort($pages, static function (array $a, array $b): int {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });

    return [
        'version' => BANDPROMO_PAGE_REGISTRY_VERSION,
        'pages' => $pages,
    ];
}

function bandpromo_page_ensure_system_pages(string $root): void
{
    $registry = bandpromo_page_load_registry($root);
    $byId = [];
    foreach ($registry['pages'] as $index => $entry) {
        if (is_array($entry)) {
            $byId[(string) ($entry['id'] ?? '')] = $index;
        }
    }

    $changed = false;

    // Heal truncated "galle" registry rows back to the system Gallery page when present.
    $galleryPath = bandpromo_page_registry_storage_root($root) . DIRECTORY_SEPARATOR . 'gallery.json';
    if (isset($byId['galle']) && is_file($galleryPath)) {
        $galleIndex = (int) $byId['galle'];
        if (!isset($byId['gallery'])) {
            $registry['pages'][$galleIndex] = [
                'id' => 'gallery',
                'title' => 'Gallery',
                'label' => 'Gallery',
                'surface' => 'player',
                'show_in_player' => true,
                'required' => false,
                'system' => true,
                'sort_order' => (int) ($registry['pages'][$galleIndex]['sort_order'] ?? 15),
            ];
            $byId['gallery'] = $galleIndex;
            unset($byId['galle']);
            $changed = true;
        } else {
            array_splice($registry['pages'], $galleIndex, 1);
            $byId = [];
            foreach ($registry['pages'] as $index => $entry) {
                if (is_array($entry)) {
                    $byId[(string) ($entry['id'] ?? '')] = $index;
                }
            }
            $changed = true;
        }
    }

    foreach (bandpromo_page_default_registry()['pages'] as $entry) {
        $id = (string) ($entry['id'] ?? '');
        if ($id === '' || isset($byId[$id])) {
            continue;
        }
        $registry['pages'][] = $entry;
        $byId[$id] = count($registry['pages']) - 1;
        $changed = true;
    }

    if ($changed) {
        bandpromo_page_write_registry($root, $registry);
    }
}

function bandpromo_page_write_registry(string $root, array $registry): void {
    bandpromo_page_registry_ensure_dir($root);
    $normalized = bandpromo_page_normalize_registry($registry);
    $path = bandpromo_page_registry_path($root);
    $payload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        throw new RuntimeException('Could not encode page registry.');
    }
    if (file_put_contents($path, $payload . "\n") === false) {
        throw new RuntimeException('Could not write page registry.');
    }
}

function bandpromo_page_load_registry(string $root): array {
    $path = bandpromo_page_registry_path($root);
    if (!is_file($path)) {
        $seedErrors = bandpromo_page_seed_registry_if_missing($root);
        if ($seedErrors !== []) {
            throw new RuntimeException('Could not seed page registry: ' . implode('; ', $seedErrors));
        }
    }

    $decoded = bandpromo_page_registry_read_json($path);
    if ($decoded === null) {
        throw new RuntimeException('Invalid page registry file: data/pages/registry.json');
    }

    return bandpromo_page_normalize_registry($decoded);
}

function bandpromo_page_seed_registry_if_missing(string $root): array {
    $path = bandpromo_page_registry_path($root);
    if (is_file($path)) {
        $decoded = bandpromo_page_registry_read_json($path);
        return $decoded === null ? ['Invalid page registry file: ' . $path] : [];
    }

    bandpromo_page_registry_ensure_dir($root);
    $templatePath = bandpromo_page_registry_template_path($root);
    $registry = bandpromo_page_default_registry();

    if (is_file($templatePath)) {
        $decoded = bandpromo_page_registry_read_json($templatePath);
        if ($decoded !== null) {
            try {
                $registry = bandpromo_page_normalize_registry($decoded);
            } catch (Throwable $throwable) {
                return [$throwable->getMessage()];
            }
        }
    }

    try {
        bandpromo_page_write_registry($root, $registry);
    } catch (Throwable $throwable) {
        return [$throwable->getMessage()];
    }

    return [];
}

function bandpromo_page_registry_entries(string $root): array {
    return bandpromo_page_load_registry($root)['pages'] ?? [];
}

function bandpromo_page_registry_entry(string $root, string $pageId): ?array {
    $pageId = bandpromo_page_normalize_id($pageId);
    foreach (bandpromo_page_registry_entries($root) as $entry) {
        if (($entry['id'] ?? '') === $pageId) {
            return $entry;
        }
    }

    return null;
}

function bandpromo_page_is_allowed_id(string $pageId, string $root): bool {
    return bandpromo_page_registry_entry($root, $pageId) !== null;
}

function bandpromo_page_is_required_id(string $pageId): bool {
    return bandpromo_page_normalize_id($pageId) === BANDPROMO_PAGE_REQUIRED_ID;
}

function bandpromo_page_registry_ids(string $root): array {
    return array_map(
        static fn(array $entry): string => (string) $entry['id'],
        bandpromo_page_registry_entries($root)
    );
}

function bandpromo_page_create_blank_document(string $pageId, string $title = ''): array {
    $document = bandpromo_page_new_document($pageId, $title);
    $document['blocks'] = [
        [
            'type' => 'richtext',
            'html' => '<p>Write your text here.</p>',
        ],
    ];

    return $document;
}

function bandpromo_page_create_page(string $root, string $title, string $label = '', string $preferredId = ''): array {
    require_once __DIR__ . '/page-storage.php';

    $title = bandpromo_page_normalize_text($title, 120);
    if ($title === '') {
        throw new InvalidArgumentException('Page title is required.');
    }

    $label = bandpromo_page_normalize_text($label !== '' ? $label : $title, 32);
    $registry = bandpromo_page_load_registry($root);
    $baseId = $preferredId !== '' ? bandpromo_page_normalize_id($preferredId) : bandpromo_page_slug_from_title($title);
    if ($baseId === '' || !preg_match('/^[a-z][a-z0-9-]{0,47}$/', $baseId)) {
        throw new InvalidArgumentException('Page id is invalid. Use lowercase letters, numbers, and hyphens.');
    }

    $id = $baseId;
    $suffix = 2;
    $existing = array_flip(bandpromo_page_registry_ids($root));
    while (isset($existing[$id])) {
        $id = substr($baseId, 0, 44) . '-' . $suffix;
        $suffix++;
    }

    $maxOrder = 0;
    foreach ($registry['pages'] as $entry) {
        $maxOrder = max($maxOrder, (int) ($entry['sort_order'] ?? 0));
    }

    $registry['pages'][] = [
        'id' => $id,
        'title' => $title,
        'label' => $label,
        'surface' => 'player',
        'show_in_player' => true,
        'required' => false,
        'system' => false,
        'sort_order' => $maxOrder + 10,
    ];

    bandpromo_page_write_registry($root, $registry);
    bandpromo_page_write_json($root, bandpromo_page_create_blank_document($id, $title));

    return bandpromo_page_registry_entry($root, $id) ?? [];
}

function bandpromo_page_delete_page(string $root, string $pageId): void {
    require_once __DIR__ . '/page-storage.php';

    $pageId = bandpromo_page_normalize_id($pageId);
    if (bandpromo_page_is_required_id($pageId)) {
        throw new InvalidArgumentException('FAQ is required and cannot be removed.');
    }

    $registry = bandpromo_page_load_registry($root);
    $registry['pages'] = array_values(array_filter(
        $registry['pages'],
        static fn(array $entry): bool => ($entry['id'] ?? '') !== $pageId
    ));

    if (count($registry['pages']) === count(bandpromo_page_registry_entries($root))) {
        throw new InvalidArgumentException('Unknown page.');
    }

    bandpromo_page_write_registry($root, $registry);

    $jsonPath = bandpromo_page_json_path($root, $pageId);
    if (is_file($jsonPath) && !unlink($jsonPath)) {
        throw new RuntimeException('Could not delete page content file.');
    }
}

function bandpromo_page_update_registry_entry(string $root, string $pageId, array $changes): array {
    $pageId = bandpromo_page_normalize_id($pageId);
    $registry = bandpromo_page_load_registry($root);
    $updated = null;

    foreach ($registry['pages'] as $index => $entry) {
        if (($entry['id'] ?? '') !== $pageId) {
            continue;
        }

        if (isset($changes['title'])) {
            $entry['title'] = (string) $changes['title'];
        }
        if (isset($changes['label'])) {
            $entry['label'] = (string) $changes['label'];
        }
        if (array_key_exists('show_in_player', $changes)) {
            $entry['show_in_player'] = (bool) $changes['show_in_player'];
        }
        if (isset($changes['surface'])) {
            $entry['surface'] = (string) $changes['surface'];
        }
        if (isset($changes['sort_order'])) {
            $entry['sort_order'] = (int) $changes['sort_order'];
        }

        $normalized = bandpromo_page_normalize_registry_entry($entry, (int) ($entry['sort_order'] ?? 0));
        if ($normalized === null) {
            throw new InvalidArgumentException('Invalid page registry entry.');
        }

        $registry['pages'][$index] = $normalized;
        $updated = $normalized;
        break;
    }

    if ($updated === null) {
        throw new InvalidArgumentException('Unknown page.');
    }

    bandpromo_page_write_registry($root, $registry);

    return $updated;
}

function bandpromo_page_admin_tab_entries(string $root): array {
    $entries = bandpromo_page_registry_entries($root);
    usort($entries, static function (array $a, array $b): int {
        if (($a['id'] ?? '') === BANDPROMO_PAGE_REQUIRED_ID) {
            return -1;
        }
        if (($b['id'] ?? '') === BANDPROMO_PAGE_REQUIRED_ID) {
            return 1;
        }

        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });

    return $entries;
}

function bandpromo_page_admin_pages_map(string $root): array {
    $map = [];
    foreach (bandpromo_page_registry_entries($root) as $entry) {
        $surface = (string) ($entry['surface'] ?? 'player');
        $map[$entry['id']] = [
            'emoji' => $entry['id'] === 'faq' ? '❓' : '📝',
            'label' => (string) ($entry['label'] ?? $entry['title']),
            'title' => (string) ($entry['title'] ?? $entry['label']),
            'description' => '',
            'required' => !empty($entry['required']),
            'system' => !empty($entry['system']),
            'surface' => $surface,
            'show_in_player' => !empty($entry['show_in_player']),
        ];
    }

    return $map;
}
