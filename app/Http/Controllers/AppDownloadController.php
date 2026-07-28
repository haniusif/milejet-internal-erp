<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Serves the Android app (APK) from the login page. Public (no auth) — the
 * button only appears once an APK is present at the configured path.
 */
class AppDownloadController extends Controller
{
    private function path(): string
    {
        return (string) config('services.apk.path');
    }

    public function info(): JsonResponse
    {
        $path = $this->path();
        if (!is_file($path)) {
            return response()->json(['available' => false]);
        }
        return response()->json([
            'available'  => true,
            'size_mb'    => round(filesize($path) / 1048576, 1),
            'updated_at' => date('c', filemtime($path)),
            'version'    => config('services.apk.version'),
        ]);
    }

    public function download()
    {
        $path = $this->path();
        abort_unless(is_file($path), 404);
        return response()->download($path, 'milejet.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
