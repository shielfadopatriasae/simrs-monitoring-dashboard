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

- **Backend:** Laravel 12 (PHP 8.5)
- **Frontend:** React 18, Inertia.js
- **Styling:** Tailwind CSS v4 (Class Strategy Dark Mode)
- **Monitoring Strategy:** Guzzle HTTP Client with custom SSL & Timeout rules.

---

## ⚙️ Installation & Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/shielfadopatriasae/simrs-monitoring-dashboard.git
   cd simrs-monitoring-dashboard
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**
   Copy the example `.env` file and generate an application key:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure Endpoints**
   Open your `.env` file and paste the API URLs retrieved from your SIMRS Khanza `database.xml`. Example:
   ```env
   URLAPIBPJS="https://apijkn.bpjs-kesehatan.go.id/vclaim-rest"
   URLAPIAPLICARE="https://new-api.bpjs-kesehatan.go.id/aplicaresws"
   # Add the rest of the endpoints here...
   ```

5. **Run the Application**
   Open two terminals and start both servers:
   ```bash
   # Terminal 1: Start Laravel Backend
   php artisan serve

   # Terminal 2: Start Vite Frontend
   npm run dev
   Visit `http://localhost:8000` in your browser.

---

## 🚢 Deployment Guides

### Option 1: Docker (Recommended)
This repository is fully Dockerized with a multi-stage build (Node.js for Vite, PHP-FPM for backend).
1. Clone the repository to your server.
2. Setup your `.env` file from `.env.clone.ganti`.
3. Build and run the containers:
   ```bash
   docker compose up -d --build
   ```
4. Access your dashboard at `http://YOUR_SERVER_IP:8000`.

### Option 2: aaPanel (Production Server)
If you are using aaPanel to host your SIMRS Khanza web applications:
1. Upload the website folder to `/www/wwwroot/simrs-monitoring`.
2. Add a new **PHP Project** in aaPanel:
   - **Domain**: `monitoring.rs-anda.com` (or your preferred IP/domain)
   - **Root directory**: `/www/wwwroot/simrs-monitoring`
   - **Run directory (Project directory)**: `/public` (Crucial for Laravel!)
   - **PHP version**: `8.2` or higher.
3. Open the Site Settings -> **Site directory** -> uncheck **Anti-XSS attack (Base directory restriction)** to allow Laravel to access files outside the `public` folder.
4. Setup URL Rewrite (Pseudo-static) to **Laravel**.
5. Open aaPanel Terminal, navigate to your folder, and build the frontend assets:
   ```bash
   cd /www/wwwroot/simrs-monitoring
   composer install --no-dev
   npm install
   npm run build
   ```

---

## 🛡️ Endpoints Monitored

- **BPJS VClaim** (Klaim & SEP)
- **BPJS Aplicare** (Ketersediaan Kamar)
- **BPJS Antrean Online** (Mobile JKN RS)
- **BPJS i-Care** 
- **BPJS Apotek / PRB**
- **BPJS PCare**
- **BPJS Mobile JKN FKTP**
- **BPJS E-Klaim**
- **Kemenkes SatuSehat**
- **Kemenkes SISRUTE**
- **Kemenkes SIRS Online**
- **Kemenkes SITB**

---

## 👨‍💻 Developed By

**[Shielfado Patriasae](https://github.com/shielfadopatriasae)**  
*IT Professional & Web Developer*  
Specializing in Healthcare IT Integrations, Web Security, and Modern UI/UX.
