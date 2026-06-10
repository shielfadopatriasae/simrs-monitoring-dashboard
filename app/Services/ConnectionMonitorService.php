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
        $results   = [];

        foreach ($endpoints as $id => $config) {
            $results[$id] = $this->pingEndpoint($config);
        }

        return $results;
    }

    /**
     * TCP Socket Check — cek apakah host:port dapat dihubungi TANPA mengirim HTTP request.
     *
     * Keunggulan:
     *  - Nol API hit ke BPJS → tidak kena rate limit (limit BPJS 200 req/menit)
     *  - Tidak perlu cons_id / secret_key
     *  - Tetap akurat: jika port 443 bisa dibuka, server ONLINE
     *
     * @return array ['status', 'latency', 'errorMessage']
     */
    private function tcpCheck(string $url, int $timeout = 5): array
    {
        $parsed = parse_url($url);
        $host   = $parsed['host'] ?? null;
        $scheme = $parsed['scheme'] ?? 'https';
        $port   = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (!$host) {
            return [
                'status'       => 'ERROR',
                'latency'      => 0,
                'errorMessage' => 'URL tidak valid, host tidak dapat di-parse.',
            ];
        }

        $start  = microtime(true);
        $errno  = 0;
        $errstr = '';

        // fsockopen hanya buka TCP handshake — tidak mengirim data apapun ke API BPJS
        $fp      = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $latency = round((microtime(true) - $start) * 1000);

        if ($fp) {
            fclose($fp);
            return [
                'status'       => 'ONLINE',
                'latency'      => $latency,
                'errorMessage' => null,
            ];
        }

        // Klasifikasi kode error TCP (errno dari fsockopen)
        $msg = match(true) {
            // DNS gagal resolve (getaddrinfo) — domain tidak ada / butuh VPN
            str_contains($errstr, 'getaddrinfo') ||
            str_contains($errstr, 'Name does not resolve') ||
            str_contains($errstr, 'Name or service not known')
                                                      => "DNS Error: Domain tidak dapat ditemukan ({$host}). URL mungkin tidak valid, atau hanya bisa diakses dari jaringan VPN/intranet khusus.",
            $errno === 111                             => "TCP Error: Koneksi ditolak (port $port tertutup / firewall memblokir)",
            $errno === 110 || str_contains($errstr, 'timed out')
                                                      => "TCP Timeout: Server tidak merespons dalam {$timeout} detik",
            $errno === 113                             => 'TCP Error: No route to host — jaringan tidak dapat menjangkau server',
            str_contains($errstr, 'reset')            => 'TCP: Koneksi direset oleh WAF/firewall server',
            default                                   => "TCP Error [$errno]: $errstr",
        };

        // DNS error → status ERROR (bukan OFFLINE, masalah di konfigurasi URL)
        $status = (str_contains($errstr, 'getaddrinfo') ||
                   str_contains($errstr, 'Name does not resolve') ||
                   str_contains($errstr, 'Name or service not known'))
                  ? 'ERROR'
                  : 'OFFLINE';

        return [
            'status'       => $status,
            'latency'      => $latency,
            'errorMessage' => $msg,
        ];

    }

    public function pingEndpoint(array $config): array
    {
        $url = $config['url'];

        if (empty($url)) {
            return [
                'name'         => $config['name'],
                'category'     => $config['category'],
                'url'          => '-',
                'status'       => 'ERROR',
                'statusCode'   => 0,
                'latency'      => 0,
                'errorMessage' => 'URL belum dikonfigurasi di .env',
            ];
        }

        // Cache 30 detik per endpoint
        // 12 endpoint × 2 hit/menit = 24 hit/menit total (jauh di bawah limit BPJS 200/menit)
        $cacheKey = 'ping_result_' . md5($url);

        return Cache::remember($cacheKey, 30, function () use ($config, $url) {
            $startTime    = microtime(true);
            $status       = 'OFFLINE';
            $statusCode   = null;
            $errorMessage = null;
            $latency      = 0;
            $category     = $config['category'];

            // ---------------------------------------------------------------
            // Semua endpoint eksternal pemerintah → TCP Socket Check
            // (BPJS, SatuSehat, Sisrute, SIRS, SITB)
            //
            // Keunggulan TCP check vs HTTP request:
            //  ✅ Nol API hit → tidak kena rate limit
            //  ✅ Tidak butuh credentials (cons_id, secret, Bearer token)
            //  ✅ Tidak kena 401/400/56 karena tidak kirim request apapun
            //  ✅ Cukup akurat: port 443 bisa dibuka = server aktif
            // ---------------------------------------------------------------
            $tcpCategories = [
                // BPJS
                'VCLAIM', 'APLICARE', 'ANTROL', 'FKTP',
                'ICARE', 'PCARE', 'SMARTCLAIM', 'APOTEK',
                // Kemenkes
                'SATUSEHAT', 'SISRUTE', 'SIRS', 'SITB',
            ];

            if (in_array($category, $tcpCategories)) {
                $tcp = $this->tcpCheck($url);
                return [
                    'name'         => $config['name'],
                    'category'     => $category,
                    'url'          => $url,
                    'status'       => $tcp['status'],
                    'statusCode'   => null,   // TCP check tidak menghasilkan HTTP status code
                    'latency'      => $tcp['latency'],
                    'errorMessage' => $tcp['errorMessage'],
                ];
            }

            // HTTP request untuk non-BPJS (SatuSehat, Sisrute, SIRS, SITB, dll)
            try {
                $response = Http::withoutVerifying()
                    ->timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                        'Accept'     => 'application/json',
                    ])
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => [
                            CURLOPT_IPRESOLVE    => CURL_IPRESOLVE_V4,
                            CURLOPT_SSLVERSION   => CURL_SSLVERSION_TLSv1_2,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        ]
                    ])
                    ->get($url);

                $latency    = round((microtime(true) - $startTime) * 1000);
                $statusCode = $response->status();

                // ============================================================
                // Klasifikasi HTTP Status Code
                // 1xx/2xx/3xx → ONLINE
                // 400/401/403/405/407/429 → WARNING (server aktif, ada isu akses)
                // 404 → NOT_FOUND
                // 4xx lain / 5xx → ERROR
                // ============================================================
                if ($statusCode >= 100 && $statusCode < 400) {
                    $status = 'ONLINE';
                } elseif (in_array($statusCode, [400, 401, 403, 405, 407, 429])) {
                    $status       = 'WARNING';
                    $errorMessage = match($statusCode) {
                        400 => 'HTTP 400 - Bad Request (Server aktif, format request tidak sesuai)',
                        401 => 'HTTP 401 - Unauthorized (Server aktif, diperlukan autentikasi)',
                        403 => 'HTTP 403 - Forbidden (Server aktif, akses ditolak)',
                        405 => 'HTTP 405 - Method Not Allowed (Server aktif, coba endpoint lain)',
                        407 => 'HTTP 407 - Proxy Authentication Required',
                        429 => 'HTTP 429 - Too Many Requests (Rate limit tercapai, tunggu sebentar)',
                        default => "HTTP $statusCode"
                    };
                } elseif ($statusCode === 404) {
                    $status       = 'NOT_FOUND';
                    $errorMessage = 'HTTP 404 - Not Found (URL endpoint tidak ditemukan di server)';
                } elseif ($statusCode >= 400 && $statusCode < 500) {
                    $status       = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Client Error";
                } elseif ($statusCode === 500) {
                    $status       = 'ERROR';
                    $errorMessage = 'HTTP 500 - Internal Server Error (Bug di sisi server)';
                } elseif ($statusCode === 502) {
                    $status       = 'ERROR';
                    $errorMessage = 'HTTP 502 - Bad Gateway (Gateway / proxy bermasalah)';
                } elseif ($statusCode === 503) {
                    $status       = 'ERROR';
                    $errorMessage = 'HTTP 503 - Service Unavailable (Server kelebihan beban atau maintenance)';
                } elseif ($statusCode === 504) {
                    $status       = 'ERROR';
                    $errorMessage = 'HTTP 504 - Gateway Timeout (Gateway tidak mendapat respons dari upstream)';
                } elseif ($statusCode >= 500) {
                    $status       = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Server Error";
                } else {
                    $status       = 'ERROR';
                    $errorMessage = "HTTP $statusCode - Unknown Status";
                }
            } catch (Exception $e) {
                $latency      = round((microtime(true) - $startTime) * 1000);
                $errorMessage = $e->getMessage();

                if (str_contains($errorMessage, 'cURL error 6') || str_contains($errorMessage, 'Could not resolve host')) {
                    $status       = 'ERROR';
                    $errorMessage = 'cURL 6 - Could not resolve host: Domain tidak ditemukan di DNS. Periksa URL di .env.';
                } elseif (str_contains($errorMessage, 'cURL error 28') || str_contains($errorMessage, 'timed out')) {
                    $status       = 'OFFLINE';
                    $errorMessage = 'cURL 28 - Timeout: Server tidak merespons dalam batas waktu 10 detik.';
                } else {
                    $status       = 'OFFLINE';
                }

                Log::error("Ping failed for {$config['name']} [{$status}]: " . $e->getMessage());
            }

            return [
                'name'         => $config['name'],
                'category'     => $config['category'],
                'url'          => $url,
                'status'       => $status,
                'statusCode'   => $statusCode,
                'latency'      => $latency,
                'errorMessage' => $errorMessage,
            ];
        });
    }
}
