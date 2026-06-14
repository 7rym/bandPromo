<?php
declare(strict_types=1);

require_once __DIR__ . '/page-blocks.php';
require_once __DIR__ . '/page-renderer.php';
require_once __DIR__ . '/page-registry.php';

function bandpromo_page_storage_root(string $root): string {
    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pages';
}

function bandpromo_page_json_path(string $root, string $pageId): string {
    $pageId = bandpromo_page_normalize_id($pageId);
    return bandpromo_page_storage_root($root) . DIRECTORY_SEPARATOR . $pageId . '.json';
}

function bandpromo_page_ensure_storage_dir(string $root): void {
    bandpromo_page_registry_ensure_dir($root);
}

function bandpromo_page_read_json_file(string $path): ?array {
    return bandpromo_page_registry_read_json($path);
}

function bandpromo_page_load_document(string $root, string $pageId): array {
    if (!bandpromo_page_is_allowed_id($pageId, $root)) {
        throw new InvalidArgumentException('Unknown page.');
    }

    $pageId = bandpromo_page_normalize_id($pageId);
    $jsonPath = bandpromo_page_json_path($root, $pageId);

    if (!is_file($jsonPath)) {
        $seedErrors = bandpromo_page_seed_document_if_missing($root, $pageId);
        if ($seedErrors !== []) {
            throw new RuntimeException(
                'Could not seed page for data/pages/' . $pageId . '.json: ' . implode('; ', $seedErrors)
            );
        }
        if (!is_file($jsonPath)) {
            throw new RuntimeException('Missing required runtime page file: data/pages/' . $pageId . '.json.');
        }
    }

    $jsonDocument = bandpromo_page_read_json_file($jsonPath);
    if ($jsonDocument === null) {
        throw new RuntimeException('Invalid runtime page JSON file: data/pages/' . $pageId . '.json');
    }

    return bandpromo_page_normalize_document($jsonDocument, $pageId);
}

function bandpromo_page_write_json(string $root, array $document): void {
    bandpromo_page_ensure_storage_dir($root);
    $pageId = bandpromo_page_normalize_id((string) ($document['id'] ?? ''));
    $jsonPath = bandpromo_page_json_path($root, $pageId);
    $payload = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        throw new RuntimeException('Could not encode page document.');
    }

    if (file_put_contents($jsonPath, $payload . "\n") === false) {
        throw new RuntimeException('Could not write page JSON for ' . $pageId . '.');
    }
}

function bandpromo_page_save_document(string $root, array $document): array {
    $pageId = bandpromo_page_normalize_id((string) ($document['id'] ?? ''));
    if (!bandpromo_page_is_allowed_id($pageId, $root)) {
        throw new InvalidArgumentException('Unknown page.');
    }

    $normalized = bandpromo_page_normalize_document($document, $pageId);
    bandpromo_page_write_json($root, $normalized);

    return [
        'document' => $normalized,
        'html' => bandpromo_page_render_document($normalized),
    ];
}

function bandpromo_page_template_path(string $root, string $pageId): string {
    $pageId = bandpromo_page_normalize_id($pageId);
    return $root . DIRECTORY_SEPARATOR . 'biblioteca' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $pageId . '.template.json';
}

function bandpromo_page_load_template_document(string $root, string $pageId): array {
    $templatePath = bandpromo_page_template_path($root, $pageId);
    if (!is_file($templatePath)) {
        throw new RuntimeException('Missing page template file: ' . $templatePath);
    }

    $decoded = bandpromo_page_read_json_file($templatePath);
    if ($decoded === null) {
        throw new RuntimeException('Invalid page template JSON: ' . $templatePath);
    }

    return bandpromo_page_normalize_document($decoded, $pageId);
}

function bandpromo_page_document_blocks_checksum(array $document): string {
    $blocks = isset($document['blocks']) && is_array($document['blocks']) ? $document['blocks'] : [];
    $payload = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($payload) ? hash('sha256', $payload) : '';
}

function bandpromo_page_matches_starter_template(string $root, string $pageId): bool {
    if (!bandpromo_page_is_allowed_id($pageId, $root)) {
        return false;
    }

    if (!is_file(bandpromo_page_template_path($root, $pageId))) {
        return false;
    }

    try {
        $templateDocument = bandpromo_page_load_template_document($root, $pageId);
        $currentDocument = bandpromo_page_load_document($root, $pageId);
    } catch (Throwable $throwable) {
        return false;
    }

    if (($currentDocument['blocks'] ?? []) === []) {
        return true;
    }

    return bandpromo_page_document_blocks_checksum($templateDocument) === bandpromo_page_document_blocks_checksum($currentDocument);
}

function bandpromo_page_runtime_present(string $root, string $pageId): bool {
    return is_file(bandpromo_page_json_path($root, $pageId));
}

function bandpromo_page_seed_document_if_missing(string $root, string $pageId): array {
    $errors = [];

    if (!bandpromo_page_is_allowed_id($pageId, $root)) {
        return ['Unknown page id: ' . $pageId];
    }

    $jsonPath = bandpromo_page_json_path($root, $pageId);
    if (is_file($jsonPath)) {
        return [];
    }

    $entry = bandpromo_page_registry_entry($root, $pageId);
    $title = (string) ($entry['title'] ?? ucfirst($pageId));

    try {
        if (is_file(bandpromo_page_template_path($root, $pageId))) {
            $templateDocument = bandpromo_page_load_template_document($root, $pageId);
            bandpromo_page_write_json($root, $templateDocument);
        } else {
            bandpromo_page_write_json($root, bandpromo_page_create_blank_document($pageId, $title));
        }
    } catch (Throwable $throwable) {
        $errors[] = $throwable->getMessage();
    }

    if (is_file($jsonPath)) {
        $decoded = bandpromo_page_read_json_file($jsonPath);
        if ($decoded === null) {
            $errors[] = 'Invalid runtime page JSON file: ' . $jsonPath;
        }
    }

    return $errors;
}

function bandpromo_page_seed_all_if_missing(string $root): array {
    $errors = bandpromo_page_seed_registry_if_missing($root);
    foreach (bandpromo_page_registry_ids($root) as $pageId) {
        $errors = array_merge($errors, bandpromo_page_seed_document_if_missing($root, $pageId));
    }

    return $errors;
}

function bandpromo_page_render_for_delivery(string $root, string $pageId): string {
    $document = bandpromo_page_load_document($root, $pageId);
    return bandpromo_page_render_document($document);
}

function bandpromo_page_editor_document(array $document, string $root): array {
    $document['blocks'] = bandpromo_page_migrate_blocks(
        isset($document['blocks']) && is_array($document['blocks']) ? $document['blocks'] : []
    );

    return $document;
}
