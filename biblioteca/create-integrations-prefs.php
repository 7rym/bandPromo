<?php
declare(strict_types=1);

/**
 * Create Preferences: price tiers + curated LLM / image / video catalogs.
 *
 * Source of truth: biblioteca/create-model-catalog.json
 * Refresh rules: biblioteca/create-model-catalog-instructions.md
 * Media models must accept at least one reference image (logo / still).
 * Video models must accept first + last frame in one request.
 */

/**
 * @return list<string>
 */
function bandpromo_create_integrations_price_tiers(): array
{
    return ['cheap', 'good', 'premium'];
}

function bandpromo_create_integrations_normalize_price_tier(string $tier): string
{
    $tier = strtolower(trim($tier));
    return in_array($tier, bandpromo_create_integrations_price_tiers(), true) ? $tier : 'cheap';
}

function bandpromo_create_integrations_price_tier_label(string $tier): string
{
    $tier = bandpromo_create_integrations_normalize_price_tier($tier);
    if ($tier === 'good') {
        return 'Good';
    }
    if ($tier === 'premium') {
        return 'Premium';
    }

    return 'Cheap';
}

/**
 * Map catalogue list keys → Preferences toggle ids.
 */
function bandpromo_create_integrations_catalog_tier_to_price_tier(string $catalogTier): string
{
    $catalogTier = strtolower(trim($catalogTier));
    if ($catalogTier === 'workhorse') {
        return 'good';
    }
    if ($catalogTier === 'premium') {
        return 'premium';
    }

    return 'cheap';
}

/**
 * @return array<string,mixed>
 */
function bandpromo_create_integrations_catalog_raw(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $path = __DIR__ . DIRECTORY_SEPARATOR . 'create-model-catalog.json';
    if (!is_file($path)) {
        $cached = [];
        return $cached;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        $cached = [];
        return $cached;
    }

    $decoded = json_decode($raw, true);
    $cached = is_array($decoded) ? $decoded : [];

    return $cached;
}

function bandpromo_create_integrations_catalog_llm_endpoint(): string
{
    $catalog = bandpromo_create_integrations_catalog_raw();
    $defaults = is_array($catalog['defaults'] ?? null) ? $catalog['defaults'] : [];
    $endpoint = trim((string) ($defaults['llm_fal_endpoint'] ?? 'openrouter/router'));

    return $endpoint !== '' ? $endpoint : 'openrouter/router';
}

function bandpromo_create_integrations_slugify_token(string $value): string
{
    $value = strtolower(trim($value));
    // Keep dots in version numbers (e.g. claude-opus-5.5, glm-5.3-flash).
    $value = preg_replace('/[^a-z0-9.]+/i', '-', $value) ?? '';
    $value = trim($value, '-.');

    return $value !== '' ? $value : 'model';
}

function bandpromo_create_integrations_openrouter_supplier_slug(string $supplier): string
{
    $map = [
        'z.ai' => 'z-ai',
        'inclusionai' => 'inclusionai',
        'liquid ai' => 'liquid',
        'openai' => 'openai',
        'anthropic' => 'anthropic',
        'alibaba' => 'qwen',
        'google' => 'google',
        'deepseek' => 'deepseek',
        'minimax' => 'minimax',
        'mistral ai' => 'mistralai',
        'moonshot ai' => 'moonshotai',
        'xai' => 'x-ai',
    ];
    $key = strtolower(trim($supplier));

    return $map[$key] ?? bandpromo_create_integrations_slugify_token($supplier);
}

function bandpromo_create_integrations_openrouter_model_id(string $supplier, string $model, string $source = ''): string
{
    $source = trim($source);
    // Prefer a concrete OpenRouter path when the source already points at one model page.
    if (preg_match('#openrouter\.ai/([^?\s\#]+)#i', $source, $m) === 1) {
        $path = trim((string) $m[1], '/');
        if ($path !== '' && strpos($path, '/') !== false) {
            return strtolower($path);
        }
    }

    return bandpromo_create_integrations_openrouter_supplier_slug($supplier)
        . '/'
        . bandpromo_create_integrations_slugify_token($model);
}

function bandpromo_create_integrations_format_usd(float $amount, int $digits = 4): string
{
    if ($amount <= 0) {
        return '';
    }
    $formatted = number_format($amount, $digits, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');

    return '$' . $formatted;
}

/**
 * Prefer estimate_usd; dual-read legacy comparison_* / nested price for half-migrated catalogues.
 *
 * @param array<string,mixed> $row
 */
function bandpromo_create_integrations_llm_price_hint(array $row): string
{
    $estimate = 0.0;
    if (isset($row['estimate_usd']) && is_numeric($row['estimate_usd'])) {
        $estimate = (float) $row['estimate_usd'];
    } elseif (isset($row['comparison_cost']) && is_numeric($row['comparison_cost'])) {
        $estimate = (float) $row['comparison_cost'];
    } elseif (isset($row['input_price']) && is_numeric($row['input_price'])
        && isset($row['output_price']) && is_numeric($row['output_price'])) {
        $estimate = (float) $row['input_price'] + (float) $row['output_price'];
    }
    if ($estimate > 0) {
        return '~' . bandpromo_create_integrations_format_usd($estimate) . ' / 1M in+out';
    }

    // Do not fall back to recommended_for — that belongs in the About line.
    return '';
}

/**
 * @param array<string,mixed> $row
 */
function bandpromo_create_integrations_image_price_hint(array $row): string
{
    if (isset($row['estimate_usd']) && is_numeric($row['estimate_usd']) && (float) $row['estimate_usd'] > 0) {
        return '~' . bandpromo_create_integrations_format_usd((float) $row['estimate_usd']) . ' / image';
    }

    if (isset($row['comparison_price']) && is_numeric($row['comparison_price'])) {
        $hint = '~' . bandpromo_create_integrations_format_usd((float) $row['comparison_price']) . ' / image';
        $status = trim((string) ($row['price_status'] ?? ''));
        if ($status !== '') {
            $hint .= ' · ' . $status;
        }

        return $hint;
    }

    $price = $row['price'] ?? null;
    if (is_array($price)) {
        if (isset($price['amount']) && is_numeric($price['amount'])) {
            $unit = trim((string) ($price['unit'] ?? ''));
            $hint = '~' . bandpromo_create_integrations_format_usd((float) $price['amount']);
            if ($unit !== '') {
                $hint .= ' / ' . $unit;
            } else {
                $hint .= ' / image';
            }

            return $hint;
        }
        if (isset($price['1k_output']) && is_numeric($price['1k_output'])) {
            return '~' . bandpromo_create_integrations_format_usd((float) $price['1k_output']) . ' / 1K';
        }
        if (isset($price['1k_output_with_one_reference']) && is_numeric($price['1k_output_with_one_reference'])) {
            return '~' . bandpromo_create_integrations_format_usd((float) $price['1k_output_with_one_reference']) . ' / 1K+ref';
        }
        if (isset($price['one_reference_example']) && is_numeric($price['one_reference_example'])) {
            return '~' . bandpromo_create_integrations_format_usd((float) $price['one_reference_example']) . ' / image+ref';
        }
        if (isset($price['first_output_with_new_style']) && is_numeric($price['first_output_with_new_style'])) {
            return '~' . bandpromo_create_integrations_format_usd((float) $price['first_output_with_new_style']) . ' / style+image';
        }
        if (isset($price['output_image']) && is_numeric($price['output_image'])) {
            $hint = '~' . bandpromo_create_integrations_format_usd((float) $price['output_image']) . ' / image';
            if (isset($price['each_reference_image']) && is_numeric($price['each_reference_image'])) {
                $hint .= ' + ~' . bandpromo_create_integrations_format_usd((float) $price['each_reference_image']) . ' / ref';
            }

            return $hint;
        }
        if (isset($price['first_processed_megapixel']) && is_numeric($price['first_processed_megapixel'])) {
            $hint = '~' . bandpromo_create_integrations_format_usd((float) $price['first_processed_megapixel']) . ' / first MP';
            if (isset($price['each_additional_processed_megapixel']) && is_numeric($price['each_additional_processed_megapixel'])) {
                $hint .= ' + ~' . bandpromo_create_integrations_format_usd((float) $price['each_additional_processed_megapixel']) . ' / extra MP';
            }

            return $hint;
        }
    }

    // Do not fall back to recommended_for — that belongs in the About line.
    return '';
}

/**
 * @param array<string,mixed> $row
 */
function bandpromo_create_integrations_image_estimate_usd(array $row): float
{
    if (isset($row['estimate_usd']) && is_numeric($row['estimate_usd'])) {
        return (float) $row['estimate_usd'];
    }
    if (isset($row['comparison_price']) && is_numeric($row['comparison_price'])) {
        return (float) $row['comparison_price'];
    }
    $price = $row['price'] ?? null;
    if (!is_array($price)) {
        return 0.0;
    }
    foreach (['amount', '1k_output_with_one_reference', 'one_reference_example', '1k_output', 'first_output_with_new_style'] as $key) {
        if (isset($price[$key]) && is_numeric($price[$key])) {
            return (float) $price[$key];
        }
    }

    return 0.0;
}

/**
 * @param array<string,mixed> $row
 */
function bandpromo_create_integrations_video_price_hint(array $row): string
{
    if (isset($row['estimate_usd']) && is_numeric($row['estimate_usd']) && (float) $row['estimate_usd'] > 0) {
        $hint = '~' . bandpromo_create_integrations_format_usd((float) $row['estimate_usd']) . ' / s';
        $res = trim((string) ($row['estimate_resolution'] ?? $row['comparison_resolution'] ?? ''));
        if ($res !== '') {
            $hint .= ' · ' . $res;
        }

        return $hint;
    }

    if (isset($row['comparison_price_per_second']) && is_numeric($row['comparison_price_per_second'])) {
        $hint = '~' . bandpromo_create_integrations_format_usd((float) $row['comparison_price_per_second']) . ' / s';
        $res = trim((string) ($row['comparison_resolution'] ?? ''));
        if ($res !== '') {
            $hint .= ' · ' . $res;
        }
        $type = trim((string) ($row['price_type'] ?? ''));
        if ($type !== '') {
            $hint .= ' · ' . $type;
        }

        return $hint;
    }

    $perSecond = $row['price_per_second'] ?? null;
    if (is_array($perSecond)) {
        if (isset($perSecond['audio_disabled']) && is_numeric($perSecond['audio_disabled'])) {
            return '~' . bandpromo_create_integrations_format_usd((float) $perSecond['audio_disabled']) . ' / s';
        }
        foreach (['480p', '720p', '1080p'] as $res) {
            if (isset($perSecond[$res]) && is_numeric($perSecond[$res])) {
                return '~' . bandpromo_create_integrations_format_usd((float) $perSecond[$res]) . ' / s · ' . $res;
            }
        }
    }

    if (isset($row['other_prices_per_second']) && is_array($row['other_prices_per_second'])) {
        foreach (['480p', '720p', '1080p'] as $res) {
            if (isset($row['other_prices_per_second'][$res]) && is_numeric($row['other_prices_per_second'][$res])) {
                return '~' . bandpromo_create_integrations_format_usd((float) $row['other_prices_per_second'][$res])
                    . ' / s · ' . $res;
            }
        }
    }

    // Do not fall back to recommended_for — that belongs in the About line.
    return '';
}

/**
 * Five-second clip estimate for Start / Preferences cost labels.
 *
 * @param array<string,mixed> $row
 */
function bandpromo_create_integrations_video_estimate_usd(array $row): float
{
    if (isset($row['estimate_usd']) && is_numeric($row['estimate_usd'])) {
        return (float) $row['estimate_usd'] * 5.0;
    }
    if (isset($row['comparison_price_per_second']) && is_numeric($row['comparison_price_per_second'])) {
        return (float) $row['comparison_price_per_second'] * 5.0;
    }
    $perSecond = $row['price_per_second'] ?? null;
    if (is_array($perSecond)) {
        if (isset($perSecond['audio_disabled']) && is_numeric($perSecond['audio_disabled'])) {
            return (float) $perSecond['audio_disabled'] * 5.0;
        }
        foreach (['480p', '720p', '1080p'] as $res) {
            if (isset($perSecond[$res]) && is_numeric($perSecond[$res])) {
                return (float) $perSecond[$res] * 5.0;
            }
        }
    }

    return 0.0;
}

function bandpromo_create_integrations_image_family_from_endpoint(string $endpoint, string $referenceType = ''): string
{
    $endpoint = strtolower(trim($endpoint));
    $referenceType = strtolower(trim($referenceType));
    if (strpos($referenceType, 'style') !== false) {
        return 'style_ref';
    }
    if (strpos($endpoint, 'gpt-image') !== false) {
        return 'openai_gpt_image';
    }
    if (strpos($endpoint, 'qwen-image-edit') !== false) {
        return 'qwen_edit';
    }
    if (strpos($endpoint, 'qwen-image-3') !== false) {
        return 'image_urls_generic';
    }
    if (strpos($endpoint, 'grok-imagine') !== false) {
        return 'grok_imagine';
    }
    if (strpos($endpoint, 'flux') !== false) {
        return 'flux_edit';
    }
    if (strpos($endpoint, 'seedream') !== false) {
        return 'seedream';
    }
    if (strpos($endpoint, 'nano-banana') !== false) {
        return 'nano_banana';
    }
    if (strpos($endpoint, 'krea/') !== false) {
        return 'krea_edit';
    }

    return 'image_urls_generic';
}

/**
 * Unified LLM catalog. route fal = openrouter/router (see catalog defaults).
 *
 * @return list<array<string,mixed>>
 */
function bandpromo_create_integrations_llm_models(): array
{
    static $rows = null;
    if (is_array($rows)) {
        return $rows;
    }

    $rows = [];
    $catalog = bandpromo_create_integrations_catalog_raw();
    $lists = is_array($catalog['lists']['llm'] ?? null) ? $catalog['lists']['llm'] : [];
    $falEndpoint = bandpromo_create_integrations_catalog_llm_endpoint();

    foreach (['cheapest', 'workhorse', 'premium'] as $catalogTier) {
        $tier = bandpromo_create_integrations_catalog_tier_to_price_tier($catalogTier);
        $entries = is_array($lists[$catalogTier] ?? null) ? $lists[$catalogTier] : [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $supplier = trim((string) ($entry['supplier'] ?? ''));
            $model = trim((string) ($entry['model'] ?? ''));
            if ($supplier === '' || $model === '') {
                continue;
            }
            $openrouterModel = bandpromo_create_integrations_openrouter_model_id(
                $supplier,
                $model,
                (string) ($entry['source'] ?? '')
            );
            $input = isset($entry['input_price']) ? (float) $entry['input_price'] : 0.0;
            $output = isset($entry['output_price']) ? (float) $entry['output_price'] : 0.0;
            // OpenAI-supplier rows stay on the native OpenAI API so Create → Start
            // connection proof keeps working; other suppliers use fal OpenRouter.
            $isOpenAi = strtolower($supplier) === 'openai';
            $providerModel = $isOpenAi
                ? bandpromo_create_integrations_slugify_token($model)
                : $openrouterModel;
            $route = $isOpenAi ? 'openai' : 'fal';
            $id = $isOpenAi ? ('openai:' . $providerModel) : ('fal:' . $providerModel);
            $rows[] = [
                'id' => $id,
                'label' => $model,
                'vendor' => $supplier,
                'route' => $route,
                'provider_model' => $providerModel,
                'openrouter_model' => $openrouterModel,
                'fal_endpoint' => $falEndpoint,
                'price_tier' => $tier,
                'price_hint' => bandpromo_create_integrations_llm_price_hint($entry),
                'input_per_mtok' => $input,
                'output_per_mtok' => $output,
                'source' => (string) ($entry['source'] ?? ''),
                'recommended_for' => (string) ($entry['recommended_for'] ?? ''),
                'note' => (string) ($entry['note'] ?? ''),
            ];
        }
    }

    return $rows;
}

/**
 * Image edit models — all require a reference image (brand logo).
 *
 * @return list<array<string,mixed>>
 */
function bandpromo_create_integrations_image_models(): array
{
    static $rows = null;
    if (is_array($rows)) {
        return $rows;
    }

    $rows = [];
    $catalog = bandpromo_create_integrations_catalog_raw();
    $lists = is_array($catalog['lists']['image'] ?? null) ? $catalog['lists']['image'] : [];

    foreach (['cheapest', 'workhorse', 'premium'] as $catalogTier) {
        $tier = bandpromo_create_integrations_catalog_tier_to_price_tier($catalogTier);
        $entries = is_array($lists[$catalogTier] ?? null) ? $lists[$catalogTier] : [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $endpoint = trim((string) ($entry['endpoint'] ?? ''));
            $supplier = trim((string) ($entry['supplier'] ?? ''));
            $model = trim((string) ($entry['model'] ?? ''));
            if ($endpoint === '' || $supplier === '' || $model === '') {
                continue;
            }
            $referenceType = trim((string) ($entry['reference_type'] ?? 'Image editing'));
            $variant = trim((string) ($entry['variant'] ?? ''));
            $rows[] = [
                'id' => $endpoint,
                'label' => $model,
                'vendor' => $supplier,
                'price_tier' => $tier,
                'price_hint' => bandpromo_create_integrations_image_price_hint($entry),
                'estimate_usd' => bandpromo_create_integrations_image_estimate_usd($entry),
                'supports_logo' => true,
                'family' => bandpromo_create_integrations_image_family_from_endpoint($endpoint, $referenceType),
                'reference_type' => $referenceType,
                'variant' => $variant,
                'maximum_reference_images' => isset($entry['maximum_reference_images'])
                    ? (int) $entry['maximum_reference_images']
                    : 0,
                'source' => (string) ($entry['source'] ?? ''),
                'recommended_for' => (string) ($entry['recommended_for'] ?? ''),
                'note' => (string) ($entry['note'] ?? ''),
            ];
        }
    }

    return $rows;
}

/**
 * Video models that accept first + last frame in one request.
 *
 * @return list<array<string,mixed>>
 */
function bandpromo_create_integrations_video_models(): array
{
    static $rows = null;
    if (is_array($rows)) {
        return $rows;
    }

    $rows = [];
    $catalog = bandpromo_create_integrations_catalog_raw();
    $lists = is_array($catalog['lists']['video'] ?? null) ? $catalog['lists']['video'] : [];

    foreach (['cheapest', 'workhorse', 'premium'] as $catalogTier) {
        $tier = bandpromo_create_integrations_catalog_tier_to_price_tier($catalogTier);
        $entries = is_array($lists[$catalogTier] ?? null) ? $lists[$catalogTier] : [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $endpoint = trim((string) ($entry['endpoint'] ?? ''));
            $supplier = trim((string) ($entry['supplier'] ?? ''));
            $model = trim((string) ($entry['model'] ?? ''));
            if ($endpoint === '' || $supplier === '' || $model === '') {
                continue;
            }
            $first = trim((string) ($entry['first_frame_parameter'] ?? 'image_url'));
            $last = trim((string) ($entry['last_frame_parameter'] ?? 'end_image_url'));
            if ($first === '') {
                $first = 'image_url';
            }
            if ($last === '') {
                $last = 'end_image_url';
            }
            $rows[] = [
                'id' => $endpoint,
                'label' => $model,
                'vendor' => $supplier,
                'price_tier' => $tier,
                'price_hint' => bandpromo_create_integrations_video_price_hint($entry),
                'estimate_usd' => bandpromo_create_integrations_video_estimate_usd($entry),
                'supports_logo' => true,
                'family' => 'first_last_frame',
                'first_frame_parameter' => $first,
                'last_frame_parameter' => $last,
                'api_availability' => (string) ($entry['api_availability'] ?? ''),
                'source' => (string) ($entry['source'] ?? ''),
                'recommended_for' => (string) ($entry['recommended_for'] ?? ''),
                'note' => (string) ($entry['note'] ?? ''),
            ];
        }
    }

    return $rows;
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function bandpromo_create_integrations_models_for_tier(array $rows, string $tier): array
{
    $tier = bandpromo_create_integrations_normalize_price_tier($tier);
    $out = [];
    foreach ($rows as $row) {
        if (($row['price_tier'] ?? '') === $tier) {
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * Map a fal OpenAI image edit endpoint to the native OpenAI Images model id.
 */
function bandpromo_create_integrations_openai_native_image_model(string $endpointOrId): string
{
    $endpointOrId = trim($endpointOrId);
    if (preg_match('#openai/(gpt-image-[^/]+)/(flare|sunburst)/edit#i', $endpointOrId, $m) === 1) {
        return strtolower($m[1] . '-' . $m[2]);
    }
    if (preg_match('#^gpt-image-#i', $endpointOrId) === 1) {
        return strtolower($endpointOrId);
    }

    return 'gpt-image-2.6-flare';
}

/**
 * Fixed Start connection-proof models (Good / workhorse). Preferences keeps the full pickers.
 *
 * @return array{
 *   openai_llm:array<string,mixed>,
 *   openai_image:array<string,mixed>,
 *   fal_llm:array<string,mixed>,
 *   fal_image:array<string,mixed>
 * }
 */
function bandpromo_create_integrations_start_proof_models(): array
{
    $openaiLlm = null;
    $falLlm = null;
    foreach (['good', 'cheap', 'premium'] as $tier) {
        foreach (bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_llm_models(), $tier) as $row) {
            $route = (string) ($row['route'] ?? '');
            if ($route === 'openai' && $openaiLlm === null) {
                $openaiLlm = $row;
            }
            if ($route === 'fal' && $falLlm === null) {
                $falLlm = $row;
            }
        }
        if ($openaiLlm !== null && $falLlm !== null) {
            break;
        }
    }

    $openaiImage = null;
    $falImage = null;
    foreach (['good', 'cheap', 'premium'] as $tier) {
        foreach (bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_image_models(), $tier) as $row) {
            $vendor = strtolower((string) ($row['vendor'] ?? ''));
            $id = (string) ($row['id'] ?? '');
            $isOpenAi = $vendor === 'openai' || strpos($id, 'openai/') === 0;
            if ($isOpenAi && $openaiImage === null) {
                $openaiImage = $row;
                $openaiImage['native_model'] = bandpromo_create_integrations_openai_native_image_model($id);
            } elseif (!$isOpenAi && $falImage === null) {
                $falImage = $row;
            }
        }
        if ($openaiImage !== null && $falImage !== null) {
            break;
        }
    }

    if ($openaiLlm === null) {
        $openaiLlm = [
            'id' => 'openai:gpt-6.1-sol',
            'label' => 'GPT-6.1 Sol',
            'vendor' => 'OpenAI',
            'route' => 'openai',
            'provider_model' => 'gpt-6.1-sol',
            'price_tier' => 'good',
            'price_hint' => 'Start proof workhorse',
        ];
    }
    if ($falLlm === null) {
        $falLlm = [
            'id' => 'fal:anthropic/claude-sonnet-5.5',
            'label' => 'Claude Sonnet 5.5',
            'vendor' => 'Anthropic',
            'route' => 'fal',
            'provider_model' => 'anthropic/claude-sonnet-5.5',
            'price_tier' => 'good',
            'price_hint' => 'Start proof workhorse',
        ];
    }
    if ($openaiImage === null) {
        $openaiImage = [
            'id' => 'openai/gpt-image-2.6/flare/edit',
            'label' => 'GPT Image 2.6 Flare Edit',
            'vendor' => 'OpenAI',
            'price_tier' => 'good',
            'price_hint' => 'Start proof workhorse',
            'native_model' => 'gpt-image-2.6-flare',
        ];
    }
    if ($falImage === null) {
        $falImage = [
            'id' => 'blackforestlabs/flux-3/edit-image',
            'label' => 'FLUX 3 Image Edit',
            'vendor' => 'Black Forest Labs',
            'price_tier' => 'good',
            'price_hint' => 'Start proof workhorse',
        ];
    }

    return [
        'openai_llm' => $openaiLlm,
        'openai_image' => $openaiImage,
        'fal_llm' => $falLlm,
        'fal_image' => $falImage,
    ];
}

function bandpromo_create_integrations_default_llm_model(string $tier = 'cheap'): string
{
    $rows = bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_llm_models(), $tier);

    return (string) ($rows[0]['id'] ?? 'fal:z-ai/glm-5.3-flash');
}

function bandpromo_create_integrations_default_image_model(string $tier = 'cheap'): string
{
    $rows = bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_image_models(), $tier);

    return (string) ($rows[0]['id'] ?? 'blackforestlabs/flux-3/klein/edit-image');
}

function bandpromo_create_integrations_default_video_model(string $tier = 'cheap'): string
{
    $rows = bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_video_models(), $tier);

    return (string) ($rows[0]['id'] ?? 'minimax/h3-max-turbo/image-to-video');
}

function bandpromo_create_integrations_normalize_llm_model(string $model, string $tier = ''): string
{
    $model = trim($model);
    $all = bandpromo_create_integrations_llm_models();
    foreach ($all as $row) {
        if ($row['id'] === $model) {
            return $model;
        }
    }
    // Legacy OpenAI-only ids and previous fal:provider/model ids.
    foreach ($all as $row) {
        if (($row['provider_model'] ?? '') === $model) {
            return (string) $row['id'];
        }
        if (($row['route'] ?? '') === 'fal' && ('fal:' . ($row['provider_model'] ?? '')) === $model) {
            return (string) $row['id'];
        }
    }
    // Map common legacy OpenAI Create ids onto the new OpenRouter-backed rows.
    $legacyOpenAi = [
        'gpt-6-luna' => 'GPT-6 Luna',
        'openai:gpt-6-luna' => 'GPT-6 Luna',
        'gpt-6-sol' => 'GPT-6.1 Sol',
        'openai:gpt-6-sol' => 'GPT-6.1 Sol',
        'gpt-6.1-sol' => 'GPT-6.1 Sol',
        'openai:gpt-6.1-sol' => 'GPT-6.1 Sol',
        'gpt-6-astra' => 'GPT-6 Astra',
        'openai:gpt-6-astra' => 'GPT-6 Astra',
    ];
    if (isset($legacyOpenAi[$model])) {
        $wanted = $legacyOpenAi[$model];
        foreach ($all as $row) {
            if (($row['label'] ?? '') === $wanted) {
                return (string) $row['id'];
            }
        }
    }

    $tier = $tier !== '' ? bandpromo_create_integrations_normalize_price_tier($tier) : 'cheap';

    return bandpromo_create_integrations_default_llm_model($tier);
}

function bandpromo_create_integrations_normalize_image_model(string $model, string $tier = ''): string
{
    $model = trim($model);
    foreach (bandpromo_create_integrations_image_models() as $row) {
        if ($row['id'] === $model) {
            return $model;
        }
    }
    $tier = $tier !== '' ? bandpromo_create_integrations_normalize_price_tier($tier) : 'cheap';

    return bandpromo_create_integrations_default_image_model($tier);
}

function bandpromo_create_integrations_normalize_video_model(string $model, string $tier = ''): string
{
    $model = trim($model);
    foreach (bandpromo_create_integrations_video_models() as $row) {
        if ($row['id'] === $model) {
            return $model;
        }
    }
    $tier = $tier !== '' ? bandpromo_create_integrations_normalize_price_tier($tier) : 'cheap';

    return bandpromo_create_integrations_default_video_model($tier);
}

/**
 * @return array<string,mixed>|null
 */
function bandpromo_create_integrations_llm_model_meta(string $model): ?array
{
    $model = bandpromo_create_integrations_normalize_llm_model($model);
    foreach (bandpromo_create_integrations_llm_models() as $row) {
        if ($row['id'] === $model) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string,mixed>|null
 */
function bandpromo_create_integrations_image_model_meta(string $model): ?array
{
    $model = bandpromo_create_integrations_normalize_image_model($model);
    foreach (bandpromo_create_integrations_image_models() as $row) {
        if ($row['id'] === $model) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string,mixed>|null
 */
function bandpromo_create_integrations_video_model_meta(string $model): ?array
{
    $model = bandpromo_create_integrations_normalize_video_model($model);
    foreach (bandpromo_create_integrations_video_models() as $row) {
        if ($row['id'] === $model) {
            return $row;
        }
    }

    return null;
}
