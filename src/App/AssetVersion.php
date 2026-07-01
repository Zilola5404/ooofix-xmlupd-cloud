<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/** Версия статики для cache-bust (?v=) */
final class AssetVersion
{
    public static function get(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $root = defined('OOOFIX_CLOUD_ROOT') ? OOOFIX_CLOUD_ROOT : dirname(__DIR__, 2);
        $versionFile = $root . '/VERSION';
        $semver = '0';
        if (is_file($versionFile)) {
            $lines = file($versionFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $semver = is_array($lines) && isset($lines[0]) ? trim($lines[0]) : '0';
        }

        $frontend = $root . '/public/frontend';
        $mtime = 0;
        if (is_dir($frontend)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($frontend, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $mtime = max($mtime, $file->getMTime());
                }
            }
        }

        $cached = $semver . '.' . $mtime;

        return $cached;
    }
}
