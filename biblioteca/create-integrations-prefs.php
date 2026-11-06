<?php
declare(strict_types=1);

/**
 * Create Start preferences: price tiers + curated LLM / image / video catalogs.
 * Media models must accept at least one reference image (logo / still).
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
 * Unified LLM catalog. route openai = OpenAI chat API; fal = fal-ai/any-llm.
 *
 * @return list<array{id:string,label:string,vendor:string,route:string,provider_model:string,price_tier:string,price_hint:string}>
 */
function bandpromo_create_integrations_llm_models(): array
{
    return [
        // Cheap
        [
            'id' => 'fal:google/gemini-2.5-flash-lite',
            'label' => 'Gemini 2.5 Flash Lite',
            'vendor' => 'Google',
            'route' => 'fal',
            'provider_model' => 'google/gemini-2.5-flash-lite',
            'price_tier' => 'cheap',
            'price_hint' => 'fal any-llm · standard',
        ],
        [
            'id' => 'openai:gpt-6-luna',
            'label' => 'GPT-6 Luna',
            'vendor' => 'OpenAI',
            'route' => 'openai',
            'provider_model' => 'gpt-6-luna',
            'price_tier' => 'cheap',
            'price_hint' => '~$0.10 / $0.50 per 1M in/out',
        ],
        [
            'id' => 'fal:openai/gpt-5-nano',
            'label' => 'GPT-5 nano (via fal)',
            'vendor' => 'OpenAI',
            'route' => 'fal',
            'provider_model' => 'openai/gpt-5-nano',
            'price_tier' => 'cheap',
            'price_hint' => 'fal any-llm · standard',
        ],
        [
            'id' => 'fal:meta-llama/llama-3.2-3b-instruct',
            'label' => 'Llama 3.2 3B',
            'vendor' => 'Meta',
            'route' => 'fal',
            'provider_model' => 'meta-llama/llama-3.2-3b-instruct',
            'price_tier' => 'cheap',
            'price_hint' => 'fal any-llm · standard',
        ],
        // Good
        [
            'id' => 'fal:google/gemini-2.5-flash',
            'label' => 'Gemini 2.5 Flash',
            'vendor' => 'Google',
            'route' => 'fal',
            'provider_model' => 'google/gemini-2.5-flash',
            'price_tier' => 'good',
            'price_hint' => 'fal any-llm · standard',
        ],
        [
            'id' => 'openai:gpt-6-sol',
            'label' => 'GPT-6 Sol',
            'vendor' => 'OpenAI',
            'route' => 'openai',
            'provider_model' => 'gpt-6-sol',
            'price_tier' => 'good',
            'price_hint' => '~$2.00 / $10.00 per 1M in/out',
        ],
        [
            'id' => 'fal:anthropic/claude-haiku-5.5',
            'label' => 'Claude Haiku 5.5',
            'vendor' => 'Anthropic',
            'route' => 'fal',
            'provider_model' => 'anthropic/claude-haiku-5.5',
            'price_tier' => 'good',
            'price_hint' => 'fal any-llm · premium rate',
        ],
        [
            'id' => 'fal:openai/gpt-5-mini',
            'label' => 'GPT-5 mini (via fal)',
            'vendor' => 'OpenAI',
            'route' => 'fal',
            'provider_model' => 'openai/gpt-5-mini',
            'price_tier' => 'good',
            'price_hint' => 'fal any-llm · standard',
        ],
        // Premium
        [
            'id' => 'fal:google/gemini-2.5-pro',
            'label' => 'Gemini 2.5 Pro',
            'vendor' => 'Google',
            'route' => 'fal',
            'provider_model' => 'google/gemini-2.5-pro',
            'price_tier' => 'premium',
            'price_hint' => 'fal any-llm · premium rate',
        ],
        [
            'id' => 'openai:gpt-6-astra',
            'label' => 'GPT-6 Astra',
            'vendor' => 'OpenAI',
            'route' => 'openai',
            'provider_model' => 'gpt-6-astra',
            'price_tier' => 'premium',
            'price_hint' => '~$10.00 / $50.00 per 1M in/out',
        ],
        [
            'id' => 'fal:anthropic/claude-sonnet-4.5',
            'label' => 'Claude Sonnet 4.5',
            'vendor' => 'Anthropic',
            'route' => 'fal',
            'provider_model' => 'anthropic/claude-sonnet-4.5',
            'price_tier' => 'premium',
            'price_hint' => 'fal any-llm · premium rate',
        ],
        [
            'id' => 'fal:openai/gpt-5-chat',
            'label' => 'GPT-5 Chat (via fal)',
            'vendor' => 'OpenAI',
            'route' => 'fal',
            'provider_model' => 'openai/gpt-5-chat',
            'price_tier' => 'premium',
            'price_hint' => 'fal any-llm · premium rate',
        ],
    ];
}

/**
 * Image edit models — all require a reference image (brand logo).
 *
 * @return list<array{id:string,label:string,vendor:string,price_tier:string,price_hint:string,estimate_usd:float,supports_logo:bool,family:string}>
 */
function bandpromo_create_integrations_image_models(): array
{
    return [
        [
            'id' => 'fal-ai/gpt-image-1.5/edit',
            'label' => 'OpenAI GPT Image 1.5',
            'vendor' => 'OpenAI',
            'price_tier' => 'cheap',
            'price_hint' => '~$0.009 / image (low quality)',
            'estimate_usd' => 0.009,
            'supports_logo' => true,
            'family' => 'openai_gpt_image',
        ],
        [
            'id' => 'meta/muse-image/edit',
            'label' => 'Meta Muse',
            'vendor' => 'Meta',
            'price_tier' => 'cheap',
            'price_hint' => '~$0.01 / image',
            'estimate_usd' => 0.01,
            'supports_logo' => true,
            'family' => 'meta_muse',
        ],
        [
            'id' => 'fal-ai/flux-2/flash/edit',
            'label' => 'FLUX 2 Flash',
            'vendor' => 'Flux',
            'price_tier' => 'cheap',
            'price_hint' => '~$0.01 / edit (1 MP in + out)',
            'estimate_usd' => 0.01,
            'supports_logo' => true,
            'family' => 'flux_flash_edit',
        ],
        [
            'id' => 'xai/grok-imagine-image/edit',
            'label' => 'Grok Imagine',
            'vendor' => 'Grok',
            'price_tier' => 'good',
            'price_hint' => '~$0.022 / image',
            'estimate_usd' => 0.022,
            'supports_logo' => true,
            'family' => 'grok_imagine',
        ],
        [
            'id' => 'fal-ai/qwen-image-edit',
            'label' => 'Qwen Image Edit',
            'vendor' => 'Alibaba',
            'price_tier' => 'good',
            'price_hint' => '~$0.03 / MP',
            'estimate_usd' => 0.03,
            'supports_logo' => true,
            'family' => 'qwen_edit',
        ],
        [
            'id' => 'fal-ai/bytedance/seedream/v5/lite/edit',
            'label' => 'Seedream 5 Lite',
            'vendor' => 'ByteDance',
            'price_tier' => 'good',
            'price_hint' => '~$0.035 / image',
            'estimate_usd' => 0.035,
            'supports_logo' => true,
            'family' => 'seedream_lite',
        ],
        [
            'id' => 'fal-ai/nano-banana-2/edit',
            'label' => 'Nano Banana 2',
            'vendor' => 'Google',
            'price_tier' => 'premium',
            'price_hint' => '~$0.06 at 0.5K',
            'estimate_usd' => 0.06,
            'supports_logo' => true,
            'family' => 'nano_banana_2_edit',
        ],
        [
            'id' => 'fal-ai/nano-banana-pro/edit',
            'label' => 'Nano Banana Pro',
            'vendor' => 'Google',
            'price_tier' => 'premium',
            'price_hint' => '~$0.15 at 1K',
            'estimate_usd' => 0.15,
            'supports_logo' => true,
            'family' => 'nano_banana_2_edit',
        ],
    ];
}

/**
 * Video models that accept a reference image (image-to-video / reference-to-video).
 *
 * @return list<array{id:string,label:string,vendor:string,price_tier:string,price_hint:string,estimate_usd:float,supports_logo:bool,family:string}>
 */
function bandpromo_create_integrations_video_models(): array
{
    return [
        [
            'id' => 'fal-ai/pixverse/v6/image-to-video',
            'label' => 'PixVerse V6',
            'vendor' => 'PixVerse',
            'price_tier' => 'cheap',
            'price_hint' => '~$0.025–0.09 / s (by resolution)',
            'estimate_usd' => 0.125,
            'supports_logo' => true,
            'family' => 'pixverse_i2v',
        ],
        [
            'id' => 'xai/grok-imagine-video/v1.5/image-to-video',
            'label' => 'Grok Imagine Video 1.5',
            'vendor' => 'Grok',
            'price_tier' => 'cheap',
            'price_hint' => '~$0.01 / s',
            'estimate_usd' => 0.05,
            'supports_logo' => true,
            'family' => 'grok_i2v',
        ],
        [
            'id' => 'bytedance/seedance-2.0/mini/image-to-video',
            'label' => 'Seedance 2.0 Mini',
            'vendor' => 'ByteDance',
            'price_tier' => 'good',
            'price_hint' => '~$0.007 / 1K tokens',
            'estimate_usd' => 0.08,
            'supports_logo' => true,
            'family' => 'seedance_i2v',
        ],
        [
            'id' => 'minimax/h3-max-turbo/image-to-video',
            'label' => 'MiniMax H3 Max Turbo',
            'vendor' => 'MiniMax',
            'price_tier' => 'good',
            'price_hint' => '~$0.025 / s',
            'estimate_usd' => 0.125,
            'supports_logo' => true,
            'family' => 'minimax_i2v',
        ],
        [
            'id' => 'xai/grok-imagine-video/reference-to-video',
            'label' => 'Grok Imagine Reference',
            'vendor' => 'Grok',
            'price_tier' => 'premium',
            'price_hint' => '~$0.05 / s',
            'estimate_usd' => 0.25,
            'supports_logo' => true,
            'family' => 'grok_ref_video',
        ],
        [
            'id' => 'fal-ai/kling-video/v2.5-turbo/pro/image-to-video',
            'label' => 'Kling 2.5 Turbo Pro',
            'vendor' => 'Kling',
            'price_tier' => 'premium',
            'price_hint' => '~$0.07 / s',
            'estimate_usd' => 0.35,
            'supports_logo' => true,
            'family' => 'kling_i2v',
        ],
    ];
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

function bandpromo_create_integrations_default_llm_model(string $tier = 'cheap'): string
{
    $rows = bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_llm_models(), $tier);

    return (string) ($rows[0]['id'] ?? 'fal:google/gemini-2.5-flash-lite');
}

function bandpromo_create_integrations_default_image_model(string $tier = 'cheap'): string
{
    $rows = bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_image_models(), $tier);

    return (string) ($rows[0]['id'] ?? 'fal-ai/gpt-image-1.5/edit');
}

function bandpromo_create_integrations_default_video_model(string $tier = 'cheap'): string
{
    $rows = bandpromo_create_integrations_models_for_tier(bandpromo_create_integrations_video_models(), $tier);

    return (string) ($rows[0]['id'] ?? 'fal-ai/pixverse/v6/image-to-video');
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
    // Legacy OpenAI-only ids.
    foreach ($all as $row) {
        if (($row['route'] ?? '') === 'openai' && ($row['provider_model'] ?? '') === $model) {
            return (string) $row['id'];
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
