<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves files from storage/app/public when public/storage symlink is missing
 * (common on shared hosting where PHP symlink() is disabled).
 */
class PublicStorageController extends Controller
{
    public function show(Request $request, string $path = ''): BinaryFileResponse
    {
        $path = str_replace('\\', '/', $path);
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $base = realpath(storage_path('app/public'));
        if ($base === false) {
            abort(404);
        }

        $file = realpath($base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($file === false || ! is_file($file) || ! str_starts_with($file, $base)) {
            abort(404);
        }

        return response()->file($file);
    }
}
