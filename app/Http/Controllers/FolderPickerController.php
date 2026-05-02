<?php

namespace App\Http\Controllers;

use App\Support\FolderPicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderPickerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $roots = FolderPicker::allowedRoots();

        if ($roots === []) {
            return response()->json([
                'error' => 'No folders are available to browse. Check PHP can read your drives and home directory, or set COURSE_BROWSE_ROOTS in .env.',
                'code' => 'no_roots',
            ], 503);
        }

        $path = $request->query('path');

        if ($path === null || $path === '') {
            return response()->json([
                'atRootList' => true,
                'current' => null,
                'parent' => null,
                'items' => collect($roots)->map(fn (string $root): array => [
                    'name' => self::labelRoot($root),
                    'path' => $root,
                ])->values()->all(),
            ]);
        }

        $resolved = realpath($path);
        if ($resolved === false || ! is_dir($resolved)) {
            return response()->json([
                'error' => 'That path does not exist or is not a folder.',
                'code' => 'invalid_path',
            ], 422);
        }

        if (! is_readable($resolved)) {
            return response()->json([
                'error' => self::permissionDeniedMessage(),
                'code' => 'permission_denied',
            ], 403);
        }

        if (! FolderPicker::isWithinAllowedRoots($resolved, $roots)) {
            return response()->json([
                'error' => 'That location is outside the allowed browse areas. Clear or widen COURSE_BROWSE_ROOTS in .env if you restricted it.',
                'code' => 'not_allowed',
            ], 403);
        }

        $scan = @scandir($resolved);
        if ($scan === false) {
            return response()->json([
                'error' => self::permissionDeniedMessage(),
                'code' => 'permission_denied',
            ], 403);
        }

        $parentPath = dirname($resolved);
        $parentReal = realpath($parentPath);
        $parent = null;
        if ($parentReal !== false && $parentReal !== $resolved && FolderPicker::isWithinAllowedRoots($parentReal, $roots)) {
            $parent = $parentReal;
        }

        $items = collect($scan)
            ->filter(fn (string $name) => $name !== '.' && $name !== '..')
            ->map(fn (string $name) => $resolved.DIRECTORY_SEPARATOR.$name)
            ->filter(fn (string $full) => is_dir($full))
            ->filter(fn (string $full) => is_readable($full))
            ->map(function (string $full) {
                $canonical = realpath($full);

                if ($canonical === false) {
                    return null;
                }

                return [
                    'name' => basename($canonical),
                    'path' => $canonical,
                ];
            })
            ->filter()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return response()->json([
            'atRootList' => false,
            'current' => $resolved,
            'parent' => $parent,
            'items' => $items,
        ]);
    }

    private static function labelRoot(string $absolute): string
    {
        $canonical = realpath($absolute) ?: $absolute;

        if (PHP_OS_FAMILY !== 'Windows' && $canonical === '/') {
            return 'Computer';
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $r = rtrim(str_replace('/', '\\', $canonical), '\\');
            if (preg_match('/^([A-Za-z]):$/', $r)) {
                return 'Drive '.strtoupper($r[0]).':';
            }
        }

        if (PHP_OS_FAMILY === 'Darwin' && str_starts_with($canonical, '/Volumes/')) {
            return basename($canonical);
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE');
        if (is_string($home) && $home !== '' && realpath($canonical) === realpath($home)) {
            return 'Home';
        }

        if (realpath($canonical) === realpath(base_path())) {
            return 'This project';
        }

        return basename($canonical) ?: $canonical;
    }

    private static function permissionDeniedMessage(): string
    {
        $base = 'PHP cannot read this folder (permission denied). Grant read access to the user that runs this app, or on macOS give Terminal / PHP “Full Disk Access” in System Settings → Privacy & Security.';

        if (PHP_OS_FAMILY === 'Darwin') {
            return $base.' Sandboxed folders (Downloads, Desktop, Documents) may require Full Disk Access.';
        }

        return $base;
    }
}
