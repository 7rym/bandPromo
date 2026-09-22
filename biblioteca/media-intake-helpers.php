<?php
declare(strict_types=1);

/**
 * Disposable media intake helpers.
 *
 * New uploads land under temp/media-intake/{audio|visual|sfx}/ with a short unique
 * prefix so concurrent uploads never collide. Registry original_filename stays the
 * human-facing safe basename (without the uniq prefix). Once a master exists and
 * has bytes, matching intake candidates (temp + durable leftovers) may be discarded.
 */

/**
 * Root folder for disposable intake staging.
 */
function bandpromo_intake_temp_root(string $root): string
{
    return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'media-intake';
}

/**
 * Ensure and return temp/media-intake/{family}/ for audio|visual|sfx.
 *
 * @throws InvalidArgumentException|RuntimeException
 */
function bandpromo_intake_temp_dir(string $root, string $family): string
{
    $family = strtolower(trim($family));
    if (!in_array($family, ['audio', 'visual', 'sfx'], true)) {
        throw new InvalidArgumentException('Intake family must be audio, visual, or sfx.');
    }

    $dir = bandpromo_intake_temp_root($root) . DIRECTORY_SEPARATOR . $family;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create media intake directory: ' . $dir);
    }

    return $dir;
}

/**
 * Prefix a safe basename with a short unique id: {uniq}_{safeName}.
 */
function bandpromo_intake_unique_basename(string $safeName): string
{
    $safeName = basename(trim($safeName));
    if ($safeName === '') {
        $safeName = 'upload.bin';
    }

    try {
        $uniq = bin2hex(random_bytes(4));
    } catch (Throwable $throwable) {
        $uniq = strtolower(dechex((int) microtime(true))) . substr(uniqid('', false), -4);
        $uniq = substr(preg_replace('/[^a-f0-9]/i', '0', $uniq) ?: '00000000', 0, 8);
    }

    return $uniq . '_' . $safeName;
}

/**
 * Strip the short unique intake prefix when present.
 * Disk names are {8hex}_{safeName}; registry uses the human safe name.
 */
function bandpromo_intake_human_basename(string $basename): string
{
    $basename = basename(trim($basename));
    if ($basename === '') {
        return '';
    }
    if (preg_match('/^[a-f0-9]{8}_(.+)$/i', $basename, $matches)) {
        return (string) $matches[1];
    }

    return $basename;
}

/**
 * Absolute path of the first existing intake candidate for this family + basename.
 * Order: temp exact → temp *_{basename} → durable leftovers (product + legacy).
 */
function bandpromo_intake_resolve_source(string $root, string $family, string $originalFilename): string
{
    $originalFilename = basename(trim($originalFilename));
    if ($originalFilename === '') {
        return '';
    }

    $family = strtolower(trim($family));
    if (!in_array($family, ['audio', 'visual', 'sfx'], true)) {
        return '';
    }

    $tempDir = bandpromo_intake_temp_root($root) . DIRECTORY_SEPARATOR . $family;
    $exact = $tempDir . DIRECTORY_SEPARATOR . $originalFilename;
    if (is_file($exact)) {
        return $exact;
    }

    if (is_dir($tempDir)) {
        $suffix = '_' . $originalFilename;
        $suffixLen = strlen($suffix);
        foreach (scandir($tempDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || strcasecmp($entry, 'desktop.ini') === 0) {
                continue;
            }
            if ($entry === $originalFilename
                || ($suffixLen > 1 && substr($entry, -$suffixLen) === $suffix)
            ) {
                $candidate = $tempDir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        $matches = glob($tempDir . DIRECTORY_SEPARATOR . '*_' . $originalFilename);
        if (is_array($matches)) {
            foreach ($matches as $match) {
                if (is_file($match)) {
                    return $match;
                }
            }
        }
    }

    require_once __DIR__ . '/discard-original-helpers.php';
    $target = $family === 'visual' ? 'visual' : $family;
    foreach (bandpromo_discard_original_candidate_paths($root, $target, $originalFilename) as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

/**
 * Unlink one explicit absolute intake path after a master exists. Never unlinks the master.
 */
function bandpromo_intake_discard_path(string $path, string $masterPath): bool
{
    $path = trim($path);
    $masterPath = trim($masterPath);
    if ($path === '' || !is_file($path)) {
        return false;
    }

    $pathReal = realpath($path) ?: $path;
    if ($masterPath !== '') {
        $masterReal = is_file($masterPath) ? (realpath($masterPath) ?: $masterPath) : $masterPath;
        if ($pathReal !== '' && $masterReal !== '' && $pathReal === $masterReal) {
            return false;
        }
    }

    return @unlink($path);
}

/**
 * After a non-empty master exists, unlink all matching intake candidates for this basename
 * (temp + durable leftovers). Never unlinks the master.
 *
 * @return bool True when at least one intake path was deleted.
 */
function bandpromo_intake_discard_after_master(
    string $root,
    string $family,
    string $originalFilename,
    string $masterPath
): bool {
    $originalFilename = basename(trim($originalFilename));
    $masterPath = trim($masterPath);
    if ($originalFilename === '' || $masterPath === '' || !is_file($masterPath)) {
        return false;
    }
    $masterSize = @filesize($masterPath);
    if ($masterSize === false || (int) $masterSize < 1) {
        return false;
    }

    $family = strtolower(trim($family));
    if (!in_array($family, ['audio', 'visual', 'sfx'], true)) {
        return false;
    }

    $masterReal = realpath($masterPath) ?: $masterPath;
    $candidates = [];

    $tempDir = bandpromo_intake_temp_root($root) . DIRECTORY_SEPARATOR . $family;
    if (is_dir($tempDir)) {
        $exact = $tempDir . DIRECTORY_SEPARATOR . $originalFilename;
        if (is_file($exact)) {
            $candidates[] = $exact;
        }
        $suffix = '_' . $originalFilename;
        $suffixLen = strlen($suffix);
        foreach (scandir($tempDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || strcasecmp($entry, 'desktop.ini') === 0) {
                continue;
            }
            if ($entry === $originalFilename
                || ($suffixLen > 1 && substr($entry, -$suffixLen) === $suffix)
            ) {
                $candidates[] = $tempDir . DIRECTORY_SEPARATOR . $entry;
            }
        }
        $matches = glob($tempDir . DIRECTORY_SEPARATOR . '*_' . $originalFilename);
        if (is_array($matches)) {
            foreach ($matches as $match) {
                $candidates[] = $match;
            }
        }
    }

    require_once __DIR__ . '/discard-original-helpers.php';
    $target = $family === 'visual' ? 'visual' : $family;
    foreach (bandpromo_discard_original_candidate_paths($root, $target, $originalFilename) as $path) {
        $candidates[] = $path;
    }

    $deletedAny = false;
    $seen = [];
    foreach ($candidates as $path) {
        if ($path === '' || isset($seen[$path])) {
            continue;
        }
        $seen[$path] = true;
        if (!is_file($path)) {
            continue;
        }
        $pathReal = realpath($path) ?: $path;
        if ($pathReal !== '' && $masterReal !== '' && $pathReal === $masterReal) {
            continue;
        }
        if (@unlink($path)) {
            $deletedAny = true;
        }
    }

    return $deletedAny;
}
