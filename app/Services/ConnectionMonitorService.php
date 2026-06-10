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

    public function pingEndpoint($config)
    {
        $url = $config['url'];
        $startTime = microtime(true);
        $status = 'OFFLINE';
        $statusCode = null;
        $errorMessage = null;

        if (empty($url)) {
            return [
                'name' => $config['name'],
                'category' => $config['category'],
                'url' => '-',
                'status' => 'ERROR',
                'statusCode' => 0,
                'latency' => 0,
                'errorMessage' => 'URL belum dikonfigurasi di database.xml'
            ];
        }

        // Gunakan Cache (selama 5 detik) untuk mencegah API blacklist/spam
        // 5 detik = maksimal 12 request per menit (sangat aman, jauh di bawah batas 200 req/menit)
        $cacheKey = 'ping_result_' . md5($url);
        
        return Cache::remember($cacheKey, 5, function () use ($config, $url) {
            $startTime = microtime(true);
            $status = 'OFFLINE';
            $statusCode = null;
            $errorMessage = null;

            $pingUrl = $url;
            
            // Tambahkan path spesifik untuk BPJS agar WAF tidak mereset koneksi (Error 56) saat mengetuk root directory
            if ($config['category'] === 'APLICARE') {
                $pingUrl = rtrim($url, '/') . '/rest/ref/kelas';
            } elseif ($config['category'] === 'VCLAIM') {
                $pingUrl = rtrim($url, '/') . '/referensi/diagnosa/A00';
            } elseif ($config['category'] === 'ANTROL' || $config['category'] === 'FKTP') {
                $pingUrl = rtrim($url, '/') . '/ref/poli';
            } elseif ($config['category'] === 'APOTEK') {
                $pingUrl = rtrim($url, '/') . '/referensi/dpho';
            } elseif ($config['category'] === 'PCARE') {
                $pingUrl = rtrim($url, '/') . '/spesialis';
            } elseif ($config['category'] === 'SMARTCLAIM') {
                $pingUrl = rtrim($url, '/') . '/referensi/diagnosa/A00';
            }

            try {
                // Konfigurasi HTTP Client:
                // 1. withoutVerifying: bypass SSL (seperti Java TrustAll)
                // 2. timeout 5 detik
                // 3. Fake User-Agent: beberapa WAF BPJS memblokir request tanpa UA (cURL error 56)
                // 4. Force IPv4: mencegah cURL error 7 di Windows/PHP karena mencoba resolve IPv6 BPJS
                $response = Http::withoutVerifying()
                    ->timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                        'Accept' => 'application/json'
                    ])
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => [
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
                        ]
                    ])
                    ->get($pingUrl);
                
                $latency = round((microtime(true) - $startTime) * 1000); // in ms
                $statusCode = $response->status();
                
                // ============================================================
                // Klasifikasi HTTP Status Code Lengkap
                // ============================================================
                // 1xx - Informational  : Server menerima request, proses berlanjut
                // 2xx - Success        : Request berhasil diproses → ONLINE
                // 3xx - Redirection    : Server aktif, tapi redirect → ONLINE
                // 4xx - Client Error   : Server aktif, tapi ada masalah akses/request
                //   400 Bad Request    → WARNING (server OK, request salah format)
                //   401 Unauthorized   → WARNING (server OK, butuh autentikasi)
                //   403 Forbidden      → WARNING (server OK, akses ditolak)
                //   404 Not Found      → NOT_FOUND (endpoint tidak ditemukan)
                //   405 Method N/A     → WARNING (server OK, method tidak didukung)
                //   429 Rate Limited   → WARNING (server OK, terlalu banyak request)
                //   4xx lainnya        → ERROR
                // 5xx - Server Error   : Server bermasalah → ERROR
                //   500 Internal Err   → ERROR (bug di server)
                //   502 Bad Gateway    → ERROR (gateway/proxy error)
                //   503 Unavailable    → ERROR (server kelebihan beban / maintenance)
                //   504 Gateway Timeout→ ERROR (gateway timeout)
                // ============================================================

                if ($statusCode >= 100 && $statusCode < 200) {
                    // 1xx Informational - server aktif
                    $status = 'ONLINE';
                } elseif ($statusCode >= 200 && $statusCode < 300) {
                    // 2xx Success - OK
                    $status = 'ONLINE';
                } elseif ($statusCode >= 300 && $statusCode < 400) {
                    // 3xx Redirect - server aktif, hanya redirect
                    $status = 'ONLINE';
                } elseif (in_array($statusCode, [400, 401, 403, 405, 407, 429])) {
                    // 4xx khusus: server AKTIF tapi ada masalah autentikasi/akses/rate limit
                    // Untuk BPJS, mendapat 401/400 = server UP, hanya perlu konsumsi token
                    $status = 'WARNING';
                    $errorMessage = match($statusCode) {
                        400 => 'HTTP 400 - Bad Request (Server aktif, format request tidak sesuai)',
                        401 => 'HTTP 401 - Unauthorized (Server aktif, diperlukan autentikasi)',
                        403 => 'HTTP 403 - Forbidden (Server aktif, akses ditolak)',
                        405 => 'HTTP 405 - Method Not Allowed (Server aktif, method tidak didukung)',
                        407 => 'HTTP 407 - Proxy Authentication Required',
                        429 => 'HTTP 429 - Too Many Requests (Server aktif, rate limit tercapai)',
                        default => "HTTP $statusCode"
                    };
                } elseif ($statusCode === 404) {
                    // 404: Endpoint tidak ditemukan
                    $status = 'NOT_FOUND';
                    $errorMessage = 'HTTP 404 - Not Found (URL endpoint tidak ditemukan di server)';
                } elseif ($statusCode >= 400 && $statusCode < 500) {
                    // 4xx lainnya: error pada sisi client
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
                    $errorMessage = 'HTTP 504 - Gateway Timeout (Gateway tidak mendapat respons dari server upstream)';
                } elseif ($statusCode >= 500) {
                    $status = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Server Error";
                } else {
                    $status = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Unknown Status";
                }
            } catch (Exception $e) {
                $latency = round((microtime(true) - $startTime) * 1000);
                $status = 'OFFLINE';
                $errorMessage = $e->getMessage();
                Log::error("Ping failed for {$config['name']}: " . $errorMessage);
            }

            return [
                'name' => $config['name'],
                'category' => $config['category'],
                'url' => $url,
                'status' => $status,
                'statusCode' => $statusCode,
                'latency' => $latency,
                'errorMessage' => $errorMessage
            ];
        });
    }
}
