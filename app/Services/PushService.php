<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging (HTTP v1) sender.
 *
 * Mints an OAuth2 access token from a service-account JSON using a pure-PHP
 * RS256 JWT (no SDK / no composer deps), then posts to the FCM v1 endpoint.
 * Safely no-ops (logs only) when no credentials are configured, so a missing
 * FCM_CREDENTIALS never breaks production.
 */
class PushService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_CACHE_KEY = 'fcm_access_token';

    public function isConfigured(): bool
    {
        $path = config('services.fcm.credentials');
        return is_string($path) && $path !== '' && is_file($path);
    }

    /**
     * Send a notification to device tokens.
     * @return array{sent:int,failed:int,invalid:array<string>}
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        $empty = ['sent' => 0, 'failed' => 0, 'invalid' => []];
        if (empty($tokens)) {
            return $empty;
        }
        if (!$this->isConfigured()) {
            Log::info('PushService: FCM not configured — skipping', ['tokens' => count($tokens), 'title' => $title]);
            return $empty;
        }

        try {
            $creds = $this->credentials();
            $accessToken = $this->accessToken($creds);
        } catch (\Throwable $e) {
            Log::error('PushService: auth failed', ['e' => $e->getMessage()]);
            return ['sent' => 0, 'failed' => count($tokens), 'invalid' => []];
        }

        $projectId = config('services.fcm.project_id') ?: ($creds['project_id'] ?? null);
        if (!$projectId) {
            Log::error('PushService: missing project_id');
            return ['sent' => 0, 'failed' => count($tokens), 'invalid' => []];
        }
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // FCM v1 data values must be strings.
        $strData = [];
        foreach ($data as $k => $v) {
            $strData[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
        }

        $sent = 0;
        $failed = 0;
        $invalid = [];
        foreach ($tokens as $token) {
            try {
                $res = Http::withToken($accessToken)->acceptJson()->timeout(10)->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $strData,
                        'android' => ['priority' => 'high'],
                    ],
                ]);
                if ($res->successful()) {
                    $sent++;
                } else {
                    $failed++;
                    $err = $res->json('error.status');
                    if ($res->status() === 404 || in_array($err, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                        $invalid[] = $token;
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('PushService: send error', ['e' => $e->getMessage()]);
            }
        }
        return ['sent' => $sent, 'failed' => $failed, 'invalid' => $invalid];
    }

    private function credentials(): array
    {
        $json = json_decode((string) file_get_contents(config('services.fcm.credentials')), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('Invalid FCM service-account JSON');
        }
        return $json;
    }

    private function accessToken(array $creds): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () use ($creds) {
            $tokenUri = $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = time();
            $jwt = $this->signJwt([
                'iss'   => $creds['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $tokenUri,
                'iat'   => $now,
                'exp'   => $now + 3600,
            ], $creds['private_key']);

            $res = Http::asForm()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);
            if (!$res->successful() || !$res->json('access_token')) {
                throw new RuntimeException('FCM OAuth failed: ' . $res->body());
            }
            return $res->json('access_token');
        });
    }

    private function signJwt(array $claims, string $privateKey): string
    {
        $input = $this->b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.' . $this->b64((string) json_encode($claims));
        $sig = '';
        if (!openssl_sign($input, $sig, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT signing failed');
        }
        return $input . '.' . $this->b64($sig);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
