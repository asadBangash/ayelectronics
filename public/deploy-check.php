<?php

/**
 * Temporary server diagnostic — open once in the browser, then DELETE this file.
 * URL: https://your-domain.com/deploy-check.php
 */
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$lines = [];
$ok = static function (bool $pass): string {
    return $pass ? 'OK' : 'FAIL';
};

$lines[] = '=== Stocky deploy check (delete deploy-check.php after use) ===';
$lines[] = 'PHP: '.PHP_VERSION.' (need >= 8.2)';
$lines[] = 'Document root: '.($_SERVER['DOCUMENT_ROOT'] ?? '(unknown)');
$lines[] = 'This script: '.__FILE__;
$lines[] = 'Project root (expected parent of public/): '.$root;
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$publicDir = rtrim(str_replace('\\', '/', __DIR__), '/');
if ($docRoot === $publicDir) {
    $lines[] = 'Document root: CORRECT (points at public/)';
} else {
    $lines[] = 'Document root: WRONG — should be exactly: '.$publicDir;
    $lines[] = '  (You are using project root; / often returns HTTP 500 via server.php rewrite.)';
}
$lines[] = '';

$lines[] = 'vendor/autoload.php: '.$ok(is_file($root.'/vendor/autoload.php'));
$lines[] = '.env file: '.$ok(is_file($root.'/.env'));
$lines[] = 'public/index.php: '.$ok(is_file(__DIR__.'/index.php'));
$lines[] = 'storefront CSS (public/js/storefront.css): '.$ok(is_file(__DIR__.'/js/storefront.css'));
$lines[] = 'storefront JS (public/js/storefront.min.js): '.$ok(is_file(__DIR__.'/js/storefront.min.js'));
$lines[] = 'storage writable: '.$ok(is_writable($root.'/storage'));
$lines[] = 'bootstrap/cache writable: '.$ok(is_writable($root.'/bootstrap/cache'));

$storageLink = __DIR__.'/storage';
$storageTarget = $root.'/storage/app/public';
if (is_link($storageLink)) {
    $lines[] = 'public/storage: symlink -> '.readlink($storageLink);
} elseif (file_exists($storageLink)) {
    $resolved = realpath($storageLink) ?: $storageLink;
    $lines[] = 'public/storage: exists, resolves to '.$resolved;
    $lines[] = '  matches storage/app/public: '.$ok(realpath($storageTarget) === $resolved);
} else {
    $lines[] = 'public/storage: MISSING (run: ln -s ../storage/app/public public/storage)';
}

$lines[] = 'storage/app/public/installed: '.$ok(is_file($storageTarget.'/installed'));

$lines[] = '';
$lines[] = 'Extensions: sodium='.(extension_loaded('sodium') ? 'yes' : 'NO (enable in hPanel)');

if (is_file($root.'/vendor/autoload.php')) {
    try {
        require $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $lines[] = 'Laravel bootstrap: OK';
        $lines[] = 'APP_ENV: '.config('app.env');
        $lines[] = 'APP_DEBUG: '.(config('app.debug') ? 'true' : 'false');
        $lines[] = 'APP_URL: '.config('app.url');

        $lines[] = '';
        $lines[] = '--- Simulated web request to / (same as homepage) ---';
        try {
            $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $request = Illuminate\Http\Request::create('/', 'GET');
            $response = $kernel->handle($request);
            $lines[] = 'HTTP status: '.$response->getStatusCode();
            if ($response->getStatusCode() >= 500) {
                $body = $response->getContent();
                $lines[] = 'Response (first 500 chars): '.substr(preg_replace('/\s+/', ' ', strip_tags($body)), 0, 500);
            }
            $kernel->terminate($request, $response);
        } catch (Throwable $e) {
            $lines[] = 'Homepage request: FAIL';
            $lines[] = get_class($e).': '.$e->getMessage();
            $lines[] = 'at '.$e->getFile().':'.$e->getLine();
        }
    } catch (Throwable $e) {
        $lines[] = 'Laravel bootstrap: FAIL';
        $lines[] = $e->getMessage();
    }
} else {
    $lines[] = 'Laravel bootstrap: skipped (no vendor)';
}

$lines[] = '';
$lines[] = 'Fix wrong document root: hPanel → Websites → gtechx.globaltechxsolutions.com →';
$lines[] = '  Document root / Change folder → .../public_html/gtechx/public';

echo implode("\n", $lines);
