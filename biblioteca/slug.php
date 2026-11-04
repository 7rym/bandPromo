<?php
declare(strict_types=1);

/**
 * Platform-canon URL slug helpers.
 *
 * Auto-generated public path segments fold Unicode to plain ASCII with one
 * deterministic map (optional ICU Any-Latin; Latin-ASCII when intl exists).
 * Operators keep freedom in titles/languages; they do not pick a transliteration
 * policy. Explicit entity slug fields still win when set.
 */

/**
 * Closest-ASCII substitutions for Latin, Nordic, German, French, Polish,
 * and common Cyrillic (Ukrainian-leaning, with Russian letters covered).
 * Digraphs (æ→ae, ß→ss, ж→zh, …) beat stripping to "-".
 *
 * @return array<string, string>
 */
function bandpromo_slug_fold_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }

    $map = [
        // Nordic / Germanic
        'æ' => 'ae', 'Æ' => 'ae',
        'ø' => 'o', 'Ø' => 'o',
        'å' => 'a', 'Å' => 'a',
        'ä' => 'a', 'Ä' => 'a',
        'ö' => 'o', 'Ö' => 'o',
        'ü' => 'u', 'Ü' => 'u',
        'ß' => 'ss',
        // French / Romance
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'y',
        'ç' => 'c', 'Ç' => 'c',
        'ñ' => 'n', 'Ñ' => 'n',
        'œ' => 'oe', 'Œ' => 'oe',
        'ð' => 'd', 'Ð' => 'd',
        'þ' => 'th', 'Þ' => 'th',
        // Polish / Central European
        'ą' => 'a', 'Ą' => 'a',
        'ć' => 'c', 'Ć' => 'c',
        'ę' => 'e', 'Ę' => 'e',
        'ł' => 'l', 'Ł' => 'l',
        'ń' => 'n', 'Ń' => 'n',
        'ś' => 's', 'Ś' => 's',
        'ź' => 'z', 'Ź' => 'z',
        'ż' => 'z', 'Ż' => 'z',
        'č' => 'c', 'Č' => 'c',
        'ď' => 'd', 'Ď' => 'd',
        'ě' => 'e', 'Ě' => 'e',
        'ň' => 'n', 'Ň' => 'n',
        'ř' => 'r', 'Ř' => 'r',
        'š' => 's', 'Š' => 's',
        'ť' => 't', 'Ť' => 't',
        'ů' => 'u', 'Ů' => 'u',
        'ž' => 'z', 'Ž' => 'z',
        // Romanian / Turkish / misc Latin
        'ă' => 'a', 'Ă' => 'a',
        'ș' => 's', 'Ș' => 's', 'ş' => 's', 'Ş' => 's',
        'ț' => 't', 'Ț' => 't', 'ţ' => 't', 'Ţ' => 't',
        'ğ' => 'g', 'Ğ' => 'g',
        'ı' => 'i', 'İ' => 'i',
        // Greek (common)
        'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z',
        'η' => 'i', 'θ' => 'th', 'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm',
        'ν' => 'n', 'ξ' => 'x', 'ο' => 'o', 'π' => 'p', 'ρ' => 'r', 'σ' => 's', 'ς' => 's',
        'τ' => 't', 'υ' => 'y', 'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o',
        // Cyrillic (Ukrainian-leaning + Russian)
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g', 'д' => 'd',
        'е' => 'e', 'є' => 'ie', 'ж' => 'zh', 'з' => 'z', 'и' => 'y', 'і' => 'i',
        'ї' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
        'ь' => '', 'ю' => 'iu', 'я' => 'ia', 'ы' => 'y', 'э' => 'e', 'ё' => 'e',
        'ъ' => '',
        'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'h', 'Ґ' => 'g', 'Д' => 'd',
        'Е' => 'e', 'Є' => 'ie', 'Ж' => 'zh', 'З' => 'z', 'И' => 'y', 'І' => 'i',
        'Ї' => 'i', 'Й' => 'i', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
        'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
        'Ф' => 'f', 'Х' => 'kh', 'Ц' => 'ts', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'shch',
        'Ь' => '', 'Ю' => 'iu', 'Я' => 'ia', 'Ы' => 'y', 'Э' => 'e', 'Ё' => 'e',
        'Ъ' => '',
    ];

    return $map;
}

/**
 * Fold free text toward ASCII letters/digits (no kebab yet).
 */
function bandpromo_slug_fold_to_ascii(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (class_exists('Transliterator')) {
        static $transliterator = null;
        if ($transliterator === null) {
            $created = Transliterator::create('Any-Latin; Latin-ASCII');
            $transliterator = $created instanceof Transliterator ? $created : false;
        }
        if ($transliterator instanceof Transliterator) {
            $folded = $transliterator->transliterate($text);
            if (is_string($folded) && $folded !== '') {
                return $folded;
            }
        }
    }

    return strtr($text, bandpromo_slug_fold_map());
}

/**
 * Platform kebab slug: fold → lowercase → non [a-z0-9] to "-" → trim/cap.
 */
function bandpromo_slugify_text(string $text, int $maxLength = 64, string $emptyFallback = 'item'): string
{
    $text = trim($text);
    if ($text === '') {
        return $emptyFallback;
    }

    $slug = strtolower(bandpromo_slug_fold_to_ascii($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        return $emptyFallback;
    }

    if ($maxLength > 0 && strlen($slug) > $maxLength) {
        $slug = rtrim(substr($slug, 0, $maxLength), '-');
    }

    return $slug !== '' ? $slug : $emptyFallback;
}
