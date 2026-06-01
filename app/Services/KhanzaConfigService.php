<?php

namespace App\Services;

class KhanzaConfigService
{
    public function __construct()
    {
        // No longer needs to parse XML, values are directly in .env
    }

    public function getAllEndpoints()
    {
        return [
            'BPJS_VCLAIM' => [
                'name' => 'BPJS VClaim',
                'category' => 'VCLAIM',
                'url' => env('URLAPIBPJS'),
                'consid' => env('CONSIDAPIBPJS'),
                'secret' => env('SECRETKEYAPIBPJS'),
                'userkey' => env('USERKEYAPIBPJS'),
            ],
            'BPJS_APLICARE' => [
                'name' => 'BPJS Aplicare',
                'category' => 'APLICARE',
                'url' => env('URLAPIAPLICARE'),
                'consid' => env('CONSIDAPIAPLICARE'),
                'secret' => env('SECRETKEYAPIAPLICARE'),
                'userkey' => env('USERKEYAPIAPLICARE'),
            ],
            'BPJS_ANTROL' => [
                'name' => 'BPJS Antrean Online',
                'category' => 'ANTROL',
                'url' => env('URLAPIMOBILEJKN'),
                'consid' => env('CONSIDAPIMOBILEJKN'),
                'secret' => env('SECRETKEYAPIMOBILEJKN'),
                'userkey' => env('USERKEYAPIMOBILEJKN'),
            ],
            'BPJS_ICARE' => [
                'name' => 'BPJS i-Care',
                'category' => 'ICARE',
                'url' => env('URLAPIICARE'),
                'consid' => env('CONSIDAPIICARE'),
                'secret' => env('SECRETKEYAPIICARE'),
                'userkey' => env('USERKEYAPIICARE'),
            ],
            'SATUSEHAT' => [
                'name' => 'Kemenkes SatuSehat',
                'category' => 'SATUSEHAT',
                'url' => env('URLFHIRSATUSEHAT'),
                'consid' => env('IDSATUSEHAT'),
                'secret' => env('SECRETKEYSATUSEHAT'),
                'userkey' => null, // SatuSehat doesn't use userkey
                'auth_url' => env('URLAUTHSATUSEHAT'),
            ],
            'BPJS_APOTEK' => [
                'name' => 'BPJS Apotek / PRB',
                'category' => 'APOTEK',
                'url' => env('URLAPIAPOTEKBPJS'),
            ],
            'BPJS_PCARE' => [
                'name' => 'BPJS PCare',
                'category' => 'PCARE',
                'url' => env('URLAPIPCARE'),
            ],
            'BPJS_FKTP' => [
                'name' => 'BPJS Mobile JKN FKTP',
                'category' => 'FKTP',
                'url' => env('URLMOBILEJKNFKTP'),
            ],
            'BPJS_EKLAIM' => [
                'name' => 'BPJS E-Klaim',
                'category' => 'SMARTCLAIM',
                'url' => env('URLAPISMARTCLAIM'),
            ],
            'KEMENKES_SISRUTE' => [
                'name' => 'Kemenkes Sisrute',
                'category' => 'SISRUTE',
                'url' => env('URLAPISISRUTE'),
            ],
            'KEMENKES_SIRS' => [
                'name' => 'Kemenkes SIRS Online',
                'category' => 'SIRS',
                'url' => env('URLAPISIRS'),
            ],
            'KEMENKES_SITB' => [
                'name' => 'Kemenkes SITB (TBC)',
                'category' => 'SITB',
                'url' => env('URLAPISITT'),
            ]
        ];
    }
}
