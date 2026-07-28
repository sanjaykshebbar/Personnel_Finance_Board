<?php
// includes/api_auth.php
// Shared helper for device/server-to-server endpoints authenticated via a
// shared-secret file (no PHP session involved).

/**
 * Constant-time comparison of a provided secret against the contents of a
 * secret file. Returns false (not authorized) if the file doesn't exist.
 */
function checkApiSecret($provided, $expectedFile) {
    if (!file_exists($expectedFile)) return false;
    $expected = trim(file_get_contents($expectedFile));
    if ($expected === '') return false;
    return hash_equals($expected, (string)$provided);
}
?>
