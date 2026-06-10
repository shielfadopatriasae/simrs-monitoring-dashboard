<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ConnectionMonitorService
{
    private $configService;

    public function __construct(KhanzaConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function pingAll()
    {
        $endpoints = $this->configService->getAllEndpoints();
        $results = [];

        foreach ($endpoints as $id => $config) {
            $results[$id] = $this->pingEndpoint($config);
        }

        return $results;
    }

    /**
     * Generate BPJS API authentication headers (X-cons-id, X-timestamp, X-signature, user_key).
     * Formula signature: HMAC-SHA256(consid + "&" + timestamp, secretKey) → base64
     */
    private function buildBpjsHeaders(array $config): array
    {
        $consid  = $config['consid']  ?? null;
        $secret  = $config['secret']  ?? null;
        $userkey = $config['userkey'] ?? null;

        if (empty($consid) || empty($secret)) {
            return [];
        }

        $timestamp = time();
        $signature = base64_encode(hash_hmac('sha256', $consid . '&' . $timestamp, $secret, true));

        $headers = [
            'X-cons-id'   => $consid,
            'X-timestamp' => (string) $timestamp,
            'X-signature' => $signature,
        ];

        if (!empty($userkey)) {
            $headers['user_key'] = $userkey;
        }

        return $headers;
    }

    public function pingEndpoint($config)
    {
        $url = $config['url'];
        $startTime = microtime(true);
        $status = 'OFFLINE';
        $statusCode = null;
        $errorMessage = null;

        if (empty($url)) {
            return [
                'name'         => $config['name'],
                'category'     => $config['category'],
                'url'          => '-',
                'status'       => 'ERROR',
                'statusCode'   => 0,
                'latency'      => 0,
                'errorMessage' => 'URL belum dikonfigurasi di .env'
            ];
        }

        // Cache 5 detik = maksimal 12 request/menit (aman, di bawah limit BPJS 200 req/menit)
        $cacheKey = 'ping_result_' . md5($url);

        return Cache::remember($cacheKey, 5, function () use ($config, $url) {
            $startTime = microtime(true);
            $status = 'OFFLINE';
            $statusCode = null;
            $errorMessage = null;

            $pingUrl = $url;
            $category = $config['category'];

            // Path spesifik per kategori agar WAF BPJS tidak mereset koneksi (cURL error 56)
            $pathMap = [
                'APLICARE'   => '/rest/ref/kelas',
                'VCLAIM'     => '/referensi/diagnosa/A00',
                'ANTROL'     => '/ref/poli',
                'FKTP'       => '/ref/poli',
                'APOTEK'     => '/referensi/dpho',
                'PCARE'      => '/spesialis',
                'SMARTCLAIM' => '/referensi/diagnosa/A00',
                'ICARE'      => '/referensi/diagnosa/A00',
            ];

            if (isset($pathMap[$category])) {
                $pingUrl = rtrim($url, '/') . $pathMap[$category];
            }

            // -------------------------------------------------------
            // Bangun header autentikasi BPJS (jika credentials tersedia)
            // Kategori yang butuh BPJS Signature: VCLAIM, APLICARE,
            // ANTROL, FKTP, ICARE, PCARE, SMARTCLAIM
            // -------------------------------------------------------
            $bpjsCategories = ['VCLAIM', 'APLICARE', 'ANTROL', 'FKTP', 'ICARE', 'PCARE', 'SMARTCLAIM', 'APOTEK'];
            $authHeaders = [];

            if (in_array($category, $bpjsCategories)) {
                $authHeaders = $this->buildBpjsHeaders($config);
            }

            $requestHeaders = array_merge([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'Accept'     => 'application/json',
            ], $authHeaders);

            try {
                $response = Http::withoutVerifying()
                    ->timeout(10)
                    ->withHeaders($requestHeaders)
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => [
                            CURLOPT_IPRESOLVE    => CURL_IPRESOLVE_V4,
                            CURLOPT_SSLVERSION   => CURL_SSLVERSION_TLSv1_2,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        ]
                    ])
                    ->get($pingUrl);

                $latency    = round((microtime(true) - $startTime) * 1000);
                $statusCode = $response->status();

                // ============================================================
                // Klasifikasi HTTP Status Code Lengkap
                // ============================================================
                // 1xx - Informational  : Server menerima request → ONLINE
                // 2xx - Success        : Request berhasil diproses → ONLINE
                // 3xx - Redirection    : Server aktif, redirect → ONLINE
                // 4xx - Client Error   : Server aktif, ada masalah akses/request
                //   400 Bad Request    → WARNING (credentials mungkin salah/kadaluarsa)
                //   401 Unauthorized   → WARNING (butuh autentikasi)
                //   403 Forbidden      → WARNING (akses ditolak)
                //   404 Not Found      → NOT_FOUND (endpoint tidak ada)
                //   405 Method N/A     → WARNING (method tidak didukung)
                //   429 Rate Limited   → WARNING (rate limit tercapai)
                //   4xx lainnya        → ERROR
                // 5xx - Server Error   : Server bermasalah → ERROR
                // ============================================================

                if ($statusCode >= 100 && $statusCode < 400) {
                    $status = 'ONLINE';
                } elseif (in_array($statusCode, [400, 401, 403, 405, 407, 429])) {
                    $status = 'WARNING';
                    $hint = !empty($authHeaders) ? ' (Periksa consumer ID / secret key di .env)' : ' (Server aktif, format request tidak sesuai)';
                    $errorMessage = match($statusCode) {
                        400 => 'HTTP 400 - Bad Request' . $hint,
                        401 => 'HTTP 401 - Unauthorized (Server aktif, periksa credentials BPJS di .env)',
                        403 => 'HTTP 403 - Forbidden (Server aktif, akses ditolak / IP belum di-whitelist)',
                        405 => 'HTTP 405 - Method Not Allowed (Server aktif, coba endpoint lain)',
                        407 => 'HTTP 407 - Proxy Authentication Required',
                        429 => 'HTTP 429 - Too Many Requests (Rate limit tercapai, tunggu sebentar)',
                        default => "HTTP $statusCode"
                    };
                } elseif ($statusCode === 404) {
                    $status = 'NOT_FOUND';
                    $errorMessage = 'HTTP 404 - Not Found (URL endpoint tidak ditemukan di server)';
                } elseif ($statusCode >= 400 && $statusCode < 500) {
                    $status = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Client Error";
                } elseif ($statusCode === 500) {
                    $status = 'ERROR';
                    $errorMessage = 'HTTP 500 - Internal Server Error (Bug di sisi server)';
                } elseif ($statusCode === 502) {
                    $status = 'ERROR';
                    $errorMessage = 'HTTP 502 - Bad Gateway (Gateway / proxy bermasalah)';
                } elseif ($statusCode === 503) {
                    $status = 'ERROR';
                    $errorMessage = 'HTTP 503 - Service Unavailable (Server kelebihan beban atau maintenance)';
                } elseif ($statusCode === 504) {
                    $status = 'ERROR';
                    $errorMessage = 'HTTP 504 - Gateway Timeout (Gateway tidak mendapat respons dari upstream)';
                } elseif ($statusCode >= 500) {
                    $status = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Server Error";
                } else {
                    $status = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Unknown Status";
                }
            } catch (Exception $e) {
                $latency      = round((microtime(true) - $startTime) * 1000);
                $status       = 'OFFLINE';
                $errorMessage = $e->getMessage();
                Log::error("Ping failed for {$config['name']}: " . $errorMessage);
            }

            return [
                'name'         => $config['name'],
                'category'     => $config['category'],
                'url'          => $url,
                'status'       => $status,
                'statusCode'   => $statusCode,
                'latency'      => $latency,
                'errorMessage' => $errorMessage
            ];
        });
    }
}
