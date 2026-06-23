<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ID / driving-licence OCR for the employee form (auto-fill from a photo).
 * Returns suggested field values; the operator confirms before saving.
 */
class OcrController extends Controller
{
    public function __construct(protected OcrService $ocr) {}

    public function extract(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => 'required|string', // base64 (data-URI accepted)
            'kind'  => ['nullable', Rule::in(['iqama', 'license', 'passport'])],
        ]);

        $base64 = $data['image'];
        if (preg_match('/^data:[^;]+;base64,(.*)$/s', $base64, $m)) {
            $base64 = $m[1];
        }
        $base64 = preg_replace('/\s+/', '', $base64);

        $result = $this->ocr->extractIdentity($base64, $data['kind'] ?? 'iqama');

        return response()->json(['data' => $result]);
    }
}
