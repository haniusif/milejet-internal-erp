<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * ID / driving-licence OCR. Pluggable by `services.ocr.driver`:
 *   - 'none'          → disabled (returns configured=false)
 *   - 'tesseract'     → free, local tesseract binary (ara+eng), no key
 *   - 'google_vision' → Google Cloud Vision TEXT_DETECTION (needs OCR_API_KEY)
 *
 * Raw text is parsed into the fields the employee form expects (id number,
 * expiry date, name candidate). Parsing is best-effort — the operator confirms.
 */
class OcrService
{
    public function configured(): bool
    {
        return match (config('services.ocr.driver')) {
            'none'      => false,
            'tesseract' => true,            // local binary, no key required
            default     => !empty(config('services.ocr.key')),
        };
    }

    /**
     * @param  string  $base64  raw base64 (no data-URI prefix)
     * @param  string  $kind    'iqama' | 'license' | 'passport' | …
     * @return array{configured:bool, fields?:array, raw_text?:string, message?:string}
     */
    public function extractIdentity(string $base64, string $kind = 'iqama'): array
    {
        if (!$this->configured()) {
            return ['configured' => false, 'message' => __('OCR is not configured.')];
        }

        try {
            $text = match (config('services.ocr.driver')) {
                'tesseract'     => $this->tesseract($base64),
                'google_vision' => $this->googleVision($base64),
                default         => null,
            };
        } catch (Throwable $e) {
            report($e);
            return ['configured' => true, 'fields' => [], 'message' => __('OCR failed: :e', ['e' => $e->getMessage()])];
        }

        if ($text === null || trim($text) === '') {
            return ['configured' => true, 'fields' => [], 'raw_text' => '', 'message' => __('No text detected.')];
        }

        return [
            'configured' => true,
            'fields'     => $this->parse($text, $kind),
            'raw_text'   => $text,
        ];
    }

    /** Local tesseract OCR → full text. Free, no external calls. */
    protected function tesseract(string $base64): ?string
    {
        $bytes = base64_decode($base64, true);
        if ($bytes === false) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'mj_ocr_');
        file_put_contents($tmp, $bytes);

        try {
            // tesseract <image> stdout -l ara+eng  (leptonica autodetects the format)
            $result = Process::timeout(60)->run([
                config('services.ocr.tesseract_bin', 'tesseract'),
                $tmp, 'stdout',
                '-l', config('services.ocr.tesseract_langs', 'ara+eng'),
            ]);
            if (!$result->successful()) {
                throw new RuntimeException($result->errorOutput() ?: 'tesseract failed');
            }
            return $result->output();
        } finally {
            @unlink($tmp);
        }
    }

    /** Cloud Vision REST call → full text. */
    protected function googleVision(string $base64): ?string
    {
        $key = config('services.ocr.key');
        $url = config('services.ocr.endpoint') ?: "https://vision.googleapis.com/v1/images:annotate?key={$key}";

        $resp = Http::timeout(30)->post($url, [
            'requests' => [[
                'image'    => ['content' => $base64],
                'features' => [['type' => 'TEXT_DETECTION']],
                // Hint both Arabic and English to help the recognizer.
                'imageContext' => ['languageHints' => ['ar', 'en']],
            ]],
        ]);
        $resp->throw();

        return data_get($resp->json(), 'responses.0.fullTextAnnotation.text');
    }

    /**
     * Pull structured fields out of raw OCR text. Returns only keys we found,
     * named to match the employee form (iqama_id, iqama_expiry_date, …).
     */
    protected function parse(string $text, string $kind): array
    {
        $fields = [];

        // Saudi national ID / Iqama: 10 digits (national starts 1, Iqama 2).
        if (preg_match('/\b([12]\d{9})\b/', $this->normalizeDigits($text), $m)) {
            $idField = $kind === 'license' ? 'license_id' : ($kind === 'passport' ? 'passport_id' : 'iqama_id');
            $fields[$idField] = $m[1];
        }

        // Gregorian dates (yyyy-mm-dd, yyyy/mm/dd, dd-mm-yyyy, dd/mm/yyyy).
        $dates = $this->extractDates($this->normalizeDigits($text));
        if ($dates) {
            // The latest future-ish date is the best expiry candidate.
            $expiry = end($dates);
            $expiryField = match ($kind) {
                'license'  => 'license_expiry_date',
                'passport' => 'passport_expiry_date',
                default    => 'iqama_expiry_date',
            };
            $fields[$expiryField] = $expiry;
        }

        // Name candidate: the longest line of mostly letters (Arabic or Latin).
        $name = $this->nameCandidate($text);
        if ($name) {
            $fields['name'] = $name;
        }

        return $fields;
    }

    /** Convert Arabic-Indic digits to ASCII so regexes work on both. */
    protected function normalizeDigits(string $s): string
    {
        $map = [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ];
        return strtr($s, $map);
    }

    /** All Gregorian-looking dates, normalised to Y-m-d and sorted ascending. */
    protected function extractDates(string $text): array
    {
        $out = [];
        if (preg_match_all('#\b(\d{4})[-/](\d{1,2})[-/](\d{1,2})\b#', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $d) {
                $out[] = sprintf('%04d-%02d-%02d', $d[1], $d[2], $d[3]);
            }
        }
        if (preg_match_all('#\b(\d{1,2})[-/](\d{1,2})[-/](\d{4})\b#', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $d) {
                $out[] = sprintf('%04d-%02d-%02d', $d[3], $d[2], $d[1]);
            }
        }
        $out = array_values(array_unique(array_filter($out, fn ($d) => checkdate(
            (int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)
        ))));
        sort($out);
        return $out;
    }

    protected function nameCandidate(string $text): ?string
    {
        $best = null;
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            // Skip lines with digits or that are too short.
            if ($line === '' || preg_match('/\d/', $line) || mb_strlen($line) < 6) {
                continue;
            }
            // Mostly letters/spaces (Arabic or Latin)?
            if (preg_match('/^[\p{Arabic}\p{Latin}\s\.]+$/u', $line) && mb_strlen($line) > mb_strlen($best ?? '')) {
                $best = $line;
            }
        }
        return $best;
    }
}
