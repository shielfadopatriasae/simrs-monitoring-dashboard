# SIMRS Khanza API Monitoring Dashboard 🏥

![SIMRS Dashboard Banner](https://img.shields.io/badge/SIMRS-Khanza-blue.svg) 
![React](https://img.shields.io/badge/React-18.x-61DAFB.svg?logo=react)
![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg?logo=laravel)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.x-38B2AC.svg?logo=tailwind-css)

A modern, real-time telemetry dashboard designed specifically for **SIMRS Khanza**. This application actively monitors the connectivity, latency, and health of 12 critical API endpoints from **BPJS Kesehatan** and **Kementerian Kesehatan (Kemenkes)**.

Built with a focus on security, performance, and UI/UX, this dashboard ensures hospital IT administrators can proactively detect network or WAF (Web Application Firewall) issues before they impact patient services.

---

## 🌟 Key Features

- **Real-time API Telemetry:** Live ping and latency tracking for 12 essential healthcare endpoints (VClaim, Aplicare, Antrean Online, SatuSehat, etc.).
- **Smart WAF Bypass & Caching:** 
  - **Auto-Caching:** Limits outbound pings to BPJS servers (e.g., max 12 requests/minute) to prevent hospital IPs from being blacklisted for spam/DDoS.
  - **Intelligent Pathing:** Simulates legitimate API traffic with `User-Agent`, forced `IPv4`, and valid endpoint paths to bypass aggressive BPJS WAF connection resets (cURL Error 56).
- **Secure Credential Extraction:** Dynamically reads and decrypts `database.xml` AES-128-CBC encryption on the backend. No secrets are ever exposed to the frontend.
- **Glassmorphism UI:** A sleek, premium dashboard featuring React, Tailwind CSS v4, dynamic Sparkline charts, and instant Light/Dark mode toggling.
- **Audio Alerts:** Automated system beeps to notify IT staff when critical services go offline.

---

## 🚀 Tech Stack

- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** React 18, Inertia.js
- **Styling:** Tailwind CSS v4 (Class Strategy Dark Mode)
- **Monitoring Strategy:** Guzzle HTTP Client with custom SSL & Timeout rules.

---

## 📋 Requirements

| Software | Minimum Version |
|----------|----------------|
| PHP      | 8.2+           |
| Composer | 2.x            |
| Node.js  | 18+            |
| NPM      | 9+             |

> **Note:** Database is NOT required. This app uses file-based caching only.

---

## ⚙️ Installation (All Platforms)

These steps apply to **every** hosting environment (aaPanel, cPanel, Docker, VPS, etc.).

### Step 1: Clone or Download
```bash
git clone https://github.com/shielfadopatriasae/simrs-monitoring-dashboard.git
cd simrs-monitoring-dashboard
```

### Step 2: Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm install
```

### Step 3: Environment Setup
```bash
cp .env.clone.ganti .env
php artisan key:generate
```
Then open `.env` and fill in your SIMRS Khanza API URLs and credentials.

### Step 4: Build Frontend
```bash
npm run build
```

### Step 5: Set Permissions (Linux/Mac)
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 🚢 Deployment Guides

### 🖥️ Option 1: Local Development (Windows/Mac/Linux)
Open two terminals:
```bash
# Terminal 1: Laravel Backend
php artisan serve

# Terminal 2: Vite Dev Server
npm run dev
```
Visit `http://localhost:8000`.

---

### 🟢 Option 2: aaPanel
1. Upload project folder to `/www/wwwroot/simrs-monitoring`.
2. Add a new **PHP Website** in aaPanel:
   - **Domain**: `monitoring.rs-anda.com` (or your IP)
   - **Root directory**: `/www/wwwroot/simrs-monitoring`
   - **Run directory**: `/public` ← **PENTING! Wajib diisi `/public`**
   - **PHP version**: `8.2` or higher
3. Open **Site Settings** → **Site directory** → uncheck **Anti-XSS attack (open_basedir)**.
4. Set **URL Rewrite (Pseudo-static)** to `Laravel 5`.
5. Open aaPanel Terminal and run:
   ```bash
   cd /www/wwwroot/simrs-monitoring
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   cp .env.clone.ganti .env
   php artisan key:generate
   chmod -R 775 storage bootstrap/cache
   ```
6. Edit `.env` with your API credentials, then visit your domain.

---

### 🟠 Option 3: cPanel (Shared Hosting)
1. Upload project to your home directory (e.g., `/home/username/simrs-monitoring`).
2. Point your domain's **Document Root** to `/home/username/simrs-monitoring/public`.
   - In cPanel → **Domains** → edit the domain → change Document Root.
3. Open **Terminal** in cPanel and run:
   ```bash
   cd ~/simrs-monitoring
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   cp .env.clone.ganti .env
   php artisan key:generate
   ```
4. Create/edit `.htaccess` in `public/` folder (usually auto-created by Laravel).
5. Edit `.env` with your API credentials.
6. Make sure your hosting supports **PHP 8.2+** and has the `curl` extension enabled.

---

### 🐳 Option 4: Docker
This repository includes a multi-stage Dockerfile (Node.js + PHP-FPM) and Nginx configuration.

```bash
# 1. Clone and setup .env
git clone https://github.com/shielfadopatriasae/simrs-monitoring-dashboard.git
cd simrs-monitoring-dashboard
cp .env.clone.ganti .env
# Edit .env with your API credentials

# 2. Build and run
docker compose up -d --build
```
Visit `http://YOUR_SERVER_IP:8000`.

---

### 🐳 Option 5: Docker Swarm
For production clusters with Docker Swarm enabled:

```bash
# 1. Build the image first
docker build -t simrs-dashboard-app:latest .

# 2. Deploy the stack
docker stack deploy -c docker-stack.yml simrs-monitor
```

---

### 🖧 Option 6: Traditional VPS (Ubuntu/Debian with Nginx)
1. Install dependencies:
   ```bash
   sudo apt update
   sudo apt install php8.2-fpm php8.2-curl php8.2-xml php8.2-zip php8.2-mbstring nginx composer nodejs npm -y
   ```
2. Clone and setup:
   ```bash
   cd /var/www
   git clone https://github.com/shielfadopatriasae/simrs-monitoring-dashboard.git
   cd simrs-monitoring-dashboard
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   cp .env.clone.ganti .env
   php artisan key:generate
   chown -R www-data:www-data storage bootstrap/cache
   ```
3. Configure Nginx:
   ```nginx
   server {
       listen 80;
       server_name monitoring.rs-anda.com;
       root /var/www/simrs-monitoring-dashboard/public;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/run/php/php8.2-fpm.sock;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```
4. Restart Nginx: `sudo systemctl restart nginx`

---

## 🛡️ Endpoints Monitored

| # | Service | Category |
|---|---------|----------|
| 1 | BPJS VClaim | Klaim & SEP |
| 2 | BPJS Aplicare | Ketersediaan Kamar |
| 3 | BPJS Antrean Online | Mobile JKN RS |
| 4 | BPJS i-Care | Rekam Medis JKN |
| 5 | BPJS Apotek / PRB | Program Rujuk Balik |
| 6 | BPJS PCare | Pelayanan FKTP |
| 7 | BPJS Mobile JKN FKTP | Antrean FKTP |
| 8 | BPJS E-Klaim | SmartClaim |
| 9 | Kemenkes SatuSehat | FHIR R4 |
| 10 | Kemenkes SISRUTE | Sistem Rujukan |
| 11 | Kemenkes SIRS Online | Pelaporan RS |
| 12 | Kemenkes SITB | Tuberkulosis |

---

## 👨‍💻 Developed By

**[Shielfado Patriasae](https://github.com/shielfadopatriasae)**  
*IT Professional & Web Developer*  
Specializing in Healthcare IT Integrations, Web Security, and Modern UI/UX.
