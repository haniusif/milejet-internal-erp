<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Temporary file uploads (authenticated). Files land in the private
 * storage/app/private/temp directory (the 'local' disk root) under a
 * randomized, safe name — not web-accessible and never executed. Returns the
 * stored path for whatever needs it next.
 */
class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50 MB (kilobytes)
        ]);

        $file = $request->file('file');
        // hashName() gives a random name; the extension is preserved but the
        // file is stored in a private disk and served back only via the API.
        $path = $file->store('temp', 'local');

        // store() returns false if the write failed (e.g. permissions) — never
        // report a success without an actual file on disk.
        if ($path === false || !Storage::disk('local')->exists($path)) {
            return response()->json([
                'message' => __('The file could not be saved on the server. Please try again.'),
            ], 500);
        }

        return response()->json([
            'data' => [
                'path'          => $path,                          // e.g. temp/AbC123.pdf
                'original_name' => $file->getClientOriginalName(),
                'size'          => $file->getSize(),
                'mime'          => $file->getClientMimeType(),
                'uploaded_by'   => $request->user()->email ?? null,
                'uploaded_at'   => now()->toIso8601String(),
                'full_path'     => Storage::disk('local')->path($path),
            ],
        ], 201);
    }
}
