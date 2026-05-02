<?php

namespace App\Support;

class FolderPicker
{
    /**
     * Top-level places shown in the picker (canonical absolute paths).
     *
     * When COURSE_BROWSE_ROOTS is empty, defaults include the whole computer:
     * macOS/Linux: "/", Windows: each readable drive (C:\, D:\, …), plus Home,
     * /Volumes (Mac), etc.
     *
     * @return list<string>
     */
    public static function allowedRoots(): array
    {
        $raw = config('course.browse_roots');

        if (is_string($raw) && trim($raw) !== '') {
            $candidates = array_map('trim', explode(',', $raw));
        } else {
            $candidates = self::defaultRootCandidates();
        }

        $out = [];
        foreach ($candidates as $path) {
            if ($path === '') {
                continue;
            }
            $real = realpath($path);
            if ($real !== false && is_dir($real) && is_readable($real)) {
                $out[] = $real;
            }
        }

        $out = array_values(array_unique($out));
        self::sortRootsForDisplay($out);

        return $out;
    }

    /**
     * @param  list<string>|null  $roots
     */
    public static function isWithinAllowedRoots(string $absolutePath, ?array $roots = null): bool
    {
        $roots ??= self::allowedRoots();
        $path = realpath($absolutePath);

        if ($path === false || ! is_dir($path)) {
            return false;
        }

        foreach ($roots as $root) {
            $rootReal = realpath($root);
            if ($rootReal === false || ! is_dir($rootReal)) {
                continue;
            }

            if ($path === $rootReal) {
                return true;
            }

            if (PHP_OS_FAMILY === 'Windows') {
                $r = rtrim(str_replace('/', '\\', $rootReal), '\\');
                $p = str_replace('/', '\\', $path);
                if (strtolower($p) === strtolower($r)) {
                    return true;
                }
                $prefix = $r.'\\';
                if (str_starts_with(strtolower($p), strtolower($prefix))) {
                    return true;
                }
            } else {
                if ($rootReal === '/') {
                    return str_starts_with($path, '/');
                }
                $prefix = rtrim($rootReal, '/').'/';
                if (str_starts_with($path, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $roots
     */
    private static function sortRootsForDisplay(array &$roots): void
    {
        usort($roots, function (string $a, string $b): int {
            $priority = function (string $r): int {
                $rr = realpath($r) ?: $r;
                if (PHP_OS_FAMILY !== 'Windows' && $rr === '/') {
                    return 0;
                }
                if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]):\\\\?$/', rtrim($rr, '\\'), $m)) {
                    return ord(strtoupper($m[1]));
                }

                return 200;
            };

            $cmp = $priority($a) <=> $priority($b);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcasecmp($a, $b);
        });
    }

    /**
     * @return list<string>
     */
    private static function defaultRootCandidates(): array
    {
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            foreach (range('C', 'Z') as $letter) {
                $drive = $letter.':\\';
                if (is_dir($drive) && is_readable($drive)) {
                    $real = realpath($drive);
                    if ($real !== false) {
                        $candidates[] = $real;
                    }
                }
            }
        } else {
            $candidates[] = '/';
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if (is_string($home) && $home !== '') {
            $candidates[] = $home;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $candidates[] = '/Volumes';
        }

        if (PHP_OS_FAMILY === 'Linux') {
            $candidates[] = '/media';
            $candidates[] = '/mnt';
        }

        $candidates[] = base_path();

        return $candidates;
    }
}
