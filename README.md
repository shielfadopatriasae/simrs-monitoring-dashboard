# 🏥 SIMRS Khanza API Monitoring Dashboard

![SIMRS Dashboard Banner](https://img.shields.io/badge/SIMRS-Khanza-blue.svg?style=for-the-badge) 
![React](https://img.shields.io/badge/React-18.x-61DAFB.svg?style=for-the-badge&logo=react)
![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg?style=for-the-badge&logo=laravel)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.x-38B2AC.svg?style=for-the-badge&logo=tailwind-css)
![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED.svg?style=for-the-badge&logo=docker)

Aplikasi **Monitoring Dashboard** yang dirancang khusus untuk memantau kesehatan (*health check*), kecepatan koneksi (*latency*), dan status keaktifan **12 API penting** dari **BPJS Kesehatan** dan **Kementerian Kesehatan (Kemenkes)** yang terintegrasi dengan **SIMRS Khanza**.

Dibuat dengan tampilan modern (*Glassmorphism UI*), responsif, dan ringan. Aplikasi ini membantu rekan-rekan **IT SIMRS / Komite Medik** di Rumah Sakit untuk mendeteksi secara dini kendala jaringan, gangguan SSL, atau blokir firewall (WAF) dari pihak BPJS/Kemenkes sebelum berdampak pada antrean pasien di lapangan.

---

## 🌟 Fitur Utama (Kenapa Harus Pakai Ini?)

*   **Telemetri Real-Time:** Memantau secara langsung kestabilan dan kecepatan respons (*ping*) ke 12 endpoint API krusial (VClaim, Aplicare, Antrean Online, SatuSehat, dll.).
*   **Bypass Proteksi WAF BPJS & Kemenkes:** 
    *   **Smart Auto-Caching:** Melakukan pembatasan permintaan (*rate-limiting*) ke server BPJS secara otomatis (maksimal request setiap 5 detik) agar IP publik Rumah Sakit Anda **tidak diblokir/blacklist** karena dianggap melakukan spam/DDoS.
    *   **Intelligent Request (Anti-Blokir):** Menggunakan *Header Spoofing* (User-Agent peramban resmi), pemaksaan koneksi *IPv4 Only*, serta protokol *TLS 1.2* / *HTTP 1.1* untuk melewati pertahanan cURL Error 56 akibat proteksi ketat firewall BPJS.
*   **Keamanan Ekstra:** Membaca data kredensial langsung dari enkripsi `database.xml` SIMRS Khanza menggunakan AES-128-CBC di sisi *backend*. Rahasia dapur rumah sakit Anda dijamin aman dan **tidak pernah bocor** ke browser pengunjung.
*   **Tampilan Premium (Glassmorphism):** Didesain dengan gaya transparan modern yang futuristik, lengkap dengan grafik pergerakan latency (*Sparkline*), indikator status warna dinamis (Hijau = Online, Merah = Offline), serta sistem sakelar Mode Terang/Gelap (*Light/Dark Mode*).
*   **Peringatan Suara (Audio Alerts):** Otomatis mengeluarkan bunyi alarm peringatan (*beep*) saat ada koneksi API penting yang tiba-tiba terputus (*OFFLINE*), sehingga staf IT bisa langsung sigap bertindak tanpa harus terus-menerus menatap layar dashboard.

---

## 🚀 Teknologi yang Digunakan

*   **Backend:** Laravel 12 (PHP 8.4-FPM)
*   **Frontend:** React 18 & Inertia.js (Bebas lag, secepat kilat)
*   **Styling:** Tailwind CSS v4 (Sangat ringan dan modern)
*   **Connection Agent:** Guzzle HTTP Client dengan aturan penanganan Timeout & SSL kustom.

---

## 📋 Persyaratan & Panduan Memilih Cara Install

Aplikasi ini sangat fleksibel dan dapat di-install di **semua jenis server** (Linux Ubuntu/Debian/CentOS, Windows Server, Proxmox, VPS, aaPanel, bahkan cPanel hosting biasa).

Silakan pilih salah satu metode instalasi yang paling sesuai dengan kondisi server di Rumah Sakit Anda:

### 🐳 [REKOMENDASI] Metode A: Menggunakan Docker / Docker Swarm (Praktis & Universal)
> [!NOTE]
> Jika Anda menggunakan Docker, Anda **TIDAK PERLU** menginstal PHP, Composer, Node.js, atau NPM di server fisik Anda! Semua kebutuhan sistem sudah dibundel rapi dan aman di dalam container.

*   **Persyaratan Server:**
    *   Sudah terpasang **Docker Engine** dan **Docker Compose**.
    *   Port `8000` (atau port lain pilihan Anda) tidak sedang digunakan oleh aplikasi lain.

---

### 🖥️ Metode B: Instalasi Tradisional (aaPanel, cPanel, VPS Tanpa Docker)
> [!IMPORTANT]
> Jika Anda memilih cara ini, Anda harus menyiapkan dan menginstal software berikut di server Anda secara manual sebelum menjalankan aplikasi.

*   **Persyaratan Software:**
    *   **PHP versi 8.2 atau 8.4** (Wajib mengaktifkan modul: `php-curl`, `php-xml`, `php-zip`, `php-mbstring`, `php-sqlite3`).
    *   **Composer 2.x** (Untuk mengunduh library PHP).
    *   **Node.js 18 ke atas** dan **NPM 9 ke atas** (Untuk merakit tampilan visual React).

> [!TIP]
> **Aplikasi ini BEBAS DATABASE EKSTERNAL!** Anda tidak perlu membuat database MySQL/MariaDB baru. Aplikasi ini menggunakan teknologi database **SQLite lokal** yang langsung tersimpan di dalam file sistem aplikasi. Sangat praktis!

---

## ⚙️ Langkah Instalasi Lengkap (Pilih Salah Satu)

---

### 🖥️ Pilihan 1: Instalasi Menggunakan Docker (Paling Mudah)

Metode ini sangat cocok untuk server tunggal (VPS/PC Server biasa) yang sudah terpasang Docker.

1.  **Unduh kode aplikasi:**
    ```bash
    git clone https://github.com/shielfadopatriasae/simrs-monitoring-dashboard.git
    cd simrs-monitoring-dashboard
    ```
2.  **Siapkan file konfigurasi:**
    ```bash
    cp .env.clone.ganti .env
    ```
    *Gunakan text editor favorit Anda (seperti `nano .env`) untuk mengedit file `.env`. Masukkan alamat IP server database Khanza Anda serta kredensial API BPJS/Kemenkes di sana.*
3.  **Jalankan aplikasi:**
    ```bash
    docker compose up -d --build
    ```
4.  **Selesai!** Buka browser Anda dan akses `http://IP_SERVER_ANDA:8000`.

---

### 🐳 Pilihan 2: Docker Swarm (Untuk Server Cluster & High Availability)

Metode ini digunakan jika server Rumah Sakit Anda menggunakan orkestrasi **Docker Swarm** untuk menjamin aplikasi tidak pernah mati.

> [!WARNING]
> **PENTING SEBELUM DEPLOY (KONDISI MULTI-NODE CLUSTER):**
> Secara default, file `docker-stack.yml` diatur menggunakan constraint `- node.role == manager` yang akan langsung bekerja lancar pada cluster Swarm tunggal (*Single Node*).
> Namun, jika cluster Swarm Anda memiliki **lebih dari satu node** (Multi-Node) dan Anda membangun image secara lokal (tidak menggunakan Private Registry/Docker Hub):
> 1. Jalankan `docker node ls` di terminal untuk melihat nama host server manager utama Anda (lihat kolom `HOSTNAME`, misal: `it`).
> 2. Buka `docker-stack.yml`, cari baris `- node.role == manager` (ada 2 tempat: di service `app` dan `web`).
> 3. Ubah baris tersebut menjadi `- node.hostname == nama_hostname_anda` (contoh: `- node.hostname == it`) agar container terkunci hanya berjalan di server tempat Anda mem-build image.

**Langkah-langkah eksekusi:**
```bash
# 1. Masuk ke folder aplikasi
cd /opt/simrs-monitoring-dashboard

# 2. Ambil versi terbaru
git pull origin main

# 3. Build image aplikasi secara lokal
docker build -t simrs-dashboard-app:latest .

# 4. Siapkan & edit file konfigurasi .env (ini berisi api bpjs, cons id, api key) jika belum ada
cp .env.clone.ganti .env
nano .env

# 5. Jalankan ke dalam Docker Swarm
docker stack deploy -c docker-stack.yml simrs-monitor
```
> [!TIP]
> **Kemudahan Ekstra di Docker Swarm:**
> - **Bebas Atur Permission SQLite:** Dockerfile kami sudah otomatis membuat database SQLite dan mengatur izin akses folder (`chown www-data`) di dalam container saat proses build.
> - **Zero-Configuration Nginx (Sangat Cocok untuk Portainer):** Konfigurasi Nginx sudah dibundel secara *inline* di dalam `docker-stack.yml`. Anda **tidak perlu lagi menyalin file `nginx.conf` fisik** ke server host. Cukup copy-paste YAML stack langsung ke Portainer UI, isi `.env`, dan deploy!

---

### 🟢 Pilihan 3: Menggunakan aaPanel

aaPanel adalah salah satu panel gratis terpopuler di kalangan IT RS. Berikut langkah termudahnya:

1.  Upload seluruh folder hasil download aplikasi ini ke server Anda, misalnya di `/www/wwwroot/simrs-monitoring`.
2.  Masuk ke menu **Website** → **Add Site**:
    *   **Domain:** Isi dengan domain Anda (misal: `monitoring.rs-anda.com`) atau IP server.
    *   **Root Directory:** `/www/wwwroot/simrs-monitoring`
    *   **PHP Version:** Pilih PHP `8.2` or higher.
3.  **Pengaturan Tambahan (PENTING!):**
    *   Klik **Site Settings** pada website yang baru dibuat.
    *   Pilih menu **Site Directory**, ubah kolom **Run Directory** menjadi `/public` lalu klik **Save**.
    *   **Matikan/Uncheck** pilihan **Anti-XSS attack (open_basedir)**.
    *   Pilih menu **URL Rewrite**, ganti pilihannya menjadi `laravel5` lalu klik **Save**.
4.  Buka **aaPanel Terminal**, jalankan perintah berikut:
    ```bash
    cd /www/wwwroot/simrs-monitoring
    composer install --no-dev --optimize-autoloader
    npm install && npm run build
    cp .env.clone.ganti .env
    php artisan key:generate
    chmod -R 775 storage bootstrap/cache database
    chown -R www-data:www-data storage bootstrap/cache database
    ```
5.  Edit file `.env` dengan kredensial Anda, lalu buka domain/IP Anda di browser.

---

### 🟠 Pilihan 4: Menggunakan cPanel (Hosting Biasa)

Bagi Rumah Sakit yang ingin menaruh dashboard monitoring ini di hosting luar agar bisa dipantau dari mana saja tanpa membebani server lokal.

1.  Upload seluruh folder aplikasi ke direktori utama cPanel Anda (sejajar dengan `public_html`, misal di `/home/username/simrs-monitoring`).
2.  Buka menu **Domains** di cPanel, buat domain/subdomain baru (misal: `monitoring.rs-anda.com`).
3.  Arahkan **Document Root** domain tersebut ke folder `/public` aplikasi Anda (misal: `/home/username/simrs-monitoring/public`).
4.  Buka **Terminal** di cPanel dan jalankan:
    ```bash
    cd ~/simrs-monitoring
    composer install --no-dev --optimize-autoloader
    npm install && npm run build
    cp .env.clone.ganti .env
    php artisan key:generate
    ```
5.  Edit file `.env` via cPanel File Manager dengan kredensial API RS Anda.
6.  *Catatan: Pastikan versi PHP di cPanel Anda sudah diatur ke 8.2 atau 8.4 melalui menu **Select PHP Version**.*

---

## 🛡️ 12 API yang Dipantau

Berikut adalah daftar lengkap 12 API eksternal yang dipantau oleh aplikasi ini secara otomatis setiap saat:

| No | Nama Layanan / API | Kategori Layanan | Keterangan |
| :--- | :--- | :--- | :--- |
| **1** | **BPJS VClaim** | Klaim & SEP | Pencetakan SEP, Rujukan, dan administrasi klaim pasien |
| **2** | **BPJS Aplicare** | Ketersediaan Kamar | Sinkronisasi jumlah bed/kamar kosong ke sistem BPJS |
| **3** | **BPJS Antrean Online** | Antrean Mobile JKN | Integrasi nomor antrean pendaftaran pasien dari Mobile JKN |
| **4** | **BPJS i-Care** | Rekam Medis JKN | Akses riwayat medis pasien BPJS secara aman |
| **5** | **BPJS Apotek / PRB** | Rujuk Balik | Pelayanan resep obat bagi pasien kronis rujuk balik |
| **6** | **BPJS PCare** | FKTP / Faskes 1 | Integrasi bridging untuk faskes tingkat pertama |
| **7** | **BPJS Mobile JKN FKTP** | Antrean FKTP | Sistem antrean online khusus klinik/puskesmas |
| **8** | **BPJS E-Klaim** | SmartClaim | Pengiriman berkas klaim digital terintegrasi |
| **9** | **Kemenkes SATUSEHAT** | Platform Integrasi | Integrasi data Resume Medis elektronik sesuai standar FHIR R4 |
| **10** | **Kemenkes SISRUTE** | Sistem Rujukan | Sistem komunikasi rujukan pasien antar rumah sakit |
| **11** | **Kemenkes SIRS Online** | Pelaporan RS | Pelaporan berkas RL (Rekap Laporan) tahunan/bulanan |
| **12** | **Kemenkes SITB** | Tuberkulosis | Pelaporan pasien terduga TB langsung ke Kemenkes |

---

## 🧑‍💻 Dikembangkan Oleh

**[Shielfado Patriasae](https://github.com/shielfadopatriasae)**  
*IT Professional & Web Developer*  
Spesialis dalam Integrasi IT Layanan Kesehatan, Keamanan Sistem Informasi, dan UI/UX Modern Berkinerja Tinggi.
