<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Create public/storage without shell exec() (shared hosts / Windows php.ini).
 */
final class PublicStorageLinker
{
    public static function needsWebFallback(): bool
    {
        return ! self::alreadyLinked(public_path('storage'), storage_path('app/public'));
    }
    /** @return list<string> */
    public static function requiredStorageDirectories(): array
    {
        return [
            storage_path('app/public'),
            storage_path('app/public/backup'),
            storage_path('app/updater'),
            storage_path('app/updates'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/testing'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
    }

    public static function ensureDirectories(): void
    {
        foreach (self::requiredStorageDirectories() as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * @return array{ok: bool, message: string, shell_hint: ?string}
     */
    public static function link(string $link, string $target, bool $relative = false, bool $force = false): array
    {
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $targetReal = realpath($target) ?: $target;

        if (file_exists($link) || is_link($link)) {
            if (self::alreadyLinked($link, $targetReal)) {
                return ['ok' => true, 'message' => 'already_linked', 'shell_hint' => null];
            }

            if (! $force) {
                return [
                    'ok' => false,
                    'message' => 'exists',
                    'shell_hint' => null,
                ];
            }

            if (is_link($link)) {
                @unlink($link);
            } elseif (is_dir($link)) {
                @rmdir($link);
            } else {
                @unlink($link);
            }
        }

        $linkTarget = $targetReal;
        if ($relative) {
            $linkTarget = self::makeRelativePath(dirname($link), $targetReal);
        }

        if (function_exists('symlink') && @symlink($linkTarget, $link)) {
            return ['ok' => true, 'message' => 'symlink', 'shell_hint' => null];
        }

        if (function_exists('symlink') && ! $relative) {
            $relativeTarget = self::makeRelativePath(dirname($link), $targetReal);
            if (@symlink($relativeTarget, $link)) {
                return ['ok' => true, 'message' => 'symlink', 'shell_hint' => null];
            }
        }

        if (self::alreadyLinked($link, $targetReal)) {
            return ['ok' => true, 'message' => 'already_linked', 'shell_hint' => null];
        }

        $hint = self::shellLinkHint($link, $targetReal);

        return [
            'ok' => false,
            'message' => function_exists('symlink') ? 'symlink_failed' : 'symlink_disabled',
            'shell_hint' => $hint,
        ];
    }

    public static function linkPublicStorage(bool $force = false): array
    {
        return self::link(public_path('storage'), storage_path('app/public'), false, $force);
    }

    public static function alreadyLinked(string $link, string $targetReal): bool
    {
        if (is_link($link)) {
            return self::pathsMatch(readlink($link) ?: '', $targetReal);
        }

        if (! file_exists($link) && ! is_dir($link)) {
            return false;
        }

        $resolved = realpath($link);
        if ($resolved && self::pathsMatch($resolved, $targetReal)) {
            return true;
        }

        if (is_dir($link) && ! is_link($link)) {
            $linkNorm = str_replace('\\', '/', $link);
            $targetNorm = str_replace('\\', '/', $targetReal);

            return rtrim($linkNorm, '/') === rtrim($targetNorm, '/');
        }

        return false;
    }

    private static function makeRelativePath(string $from, string $to): string
    {
        if (class_exists(\Symfony\Component\Filesystem\Path::class)) {
            $rel = \Symfony\Component\Filesystem\Path::makeRelative($to, $from);

            return str_replace('\\', '/', $rel === '' ? '.' : $rel);
        }

        $from = rtrim(str_replace('\\', '/', realpath($from) ?: $from), '/');
        $to = rtrim(str_replace('\\', '/', realpath($to) ?: $to), '/');

        if ($from === $to) {
            return '.';
        }

        return $to;
    }

    private static function pathsMatch(string $a, string $b): bool
    {
        $na = str_replace('\\', '/', realpath($a) ?: $a);
        $nb = str_replace('\\', '/', realpath($b) ?: $b);

        return rtrim($na, '/') === rtrim($nb, '/');
    }

    private static function shellLinkHint(string $linkPath, string $targetPath): string
    {
        $relativeTarget = self::makeRelativePath(dirname($linkPath), $targetPath);
        if ($relativeTarget === '') {
            $relativeTarget = '.';
        }

        if (windows_os()) {
            return 'setup-storage.bat  (from project root; uses mklink /J, no admin on most PCs)';
        }

        $root = str_replace('\\', '/', base_path());

        return 'cd '.escapeshellarg($root).' && rm -rf public/storage && ln -s '.escapeshellarg($root.'/storage/app/public').' public/storage';
    }

    /**
     * Copy storage/app/public into public/storage (when symlinks are forbidden).
     */
    public static function mirror(bool $force = false): array
    {
        self::ensureDirectories();

        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_link($link)) {
            if (self::alreadyLinked($link, $target)) {
                return ['ok' => true, 'message' => 'already_linked'];
            }
            if (! $force) {
                return ['ok' => false, 'message' => 'exists'];
            }
            @unlink($link);
        }

        if (is_dir($link)) {
            if (! $force && count(scandir($link) ?: []) > 2) {
                return ['ok' => false, 'message' => 'exists'];
            }
            File::deleteDirectory($link);
        }

        mkdir($link, 0755, true);
        File::copyDirectory($target, $link);

        return ['ok' => true, 'message' => 'mirrored'];
    }
}
