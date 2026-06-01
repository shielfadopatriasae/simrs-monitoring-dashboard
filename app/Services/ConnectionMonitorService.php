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
                
                // Status 301/302 juga dihitung ONLINE karena server merespon
                if ($statusCode >= 200 && $statusCode < 500) {
                    $status = 'ONLINE';
                } else {
                    $status = 'ERROR';
                    $errorMessage = "Server returned HTTP $statusCode";
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
