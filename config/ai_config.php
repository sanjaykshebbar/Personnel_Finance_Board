<?php
// config/ai_config.php
require_once __DIR__ . '/../includes/env.php';

loadEnv(__DIR__ . '/../.env');

define('AI_DEFAULT_MODEL', 'claude-haiku-4-5-20251001');

/**
 * Get the Anthropic API key from the environment, or null if not configured.
 */
function getAnthropicApiKey() {
    $key = getenv('ANTHROPIC_API_KEY');
    return ($key !== false && $key !== '') ? $key : null;
}

/**
 * Get the configured AI model, falling back to the default.
 */
function getAiModel() {
    $model = getenv('AI_MODEL');
    return ($model !== false && $model !== '') ? $model : AI_DEFAULT_MODEL;
}

/**
 * Whether AI features (import/SMS parsing) are usable right now.
 */
function isAiConfigured() {
    return getAnthropicApiKey() !== null;
}
?>
