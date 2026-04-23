# Project Installation

This repository contains two parts:

- `main_templates/my-app` - Laravel application
- root Python scripts: `app.py` and `simulate_socket_data.py`

## Prerequisites

- PHP 8.5+
- Composer
- Node.js 20+
- npm
- Python 3.10+
- MongoDB access

## Laravel App Setup

1. Go into the Laravel app folder:

   ```bash
   cd main_templates/my-app
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Install frontend dependencies:

   ```bash
   npm install
   ```

4. Configure environment:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. Set your MongoDB values in `.env`:

   ```env
   DB_CONNECTION=mongodb
   MONGODB_URI=...
   MONGODB_DATABASE=espData
   MONGODB_AUTH_DATABASE=espData
   MONGODB_COLLECTION=readings
   MONGODB_BILLING_INVOICES_BUCKET=billing_invoices
   ```

6. Run migrations or any project setup commands required by your environment.

7. Start the app:

   ```bash
   php artisan serve
   npm run dev
   ```

## Python Scripts Setup

1. Go to the repository root:

   ```bash
   cd /path/to/esp32_dashboard_project
   ```

2. Create a virtual environment:

   ```bash
   python3 -m venv .venv
   source .venv/bin/activate
   ```

3. Install Python dependencies:

   ```bash
   pip install -r requirements.txt
   ```

4. Run the Flask bridge app:

   ```bash
   python app.py
   ```

5. Run the telemetry simulator if needed:

   ```bash
   python simulate_socket_data.py
   ```

## Notes

- `requirements.txt` is only for the Python part of the repo.
- Laravel dependencies stay in `composer.json` and `package.json` inside `main_templates/my-app`.
- The invoice archive uses MongoDB GridFS bucket `billing_invoices` by default.

## ESP32 Provisioning

The ESP32 sketch lives in `cod_esp_32.ino` and now uses BLE provisioning for Wi-Fi setup on ESP32-S3.

- Bluetooth Classic is not used on ESP32-S3; provisioning is done over BLE.
- Up to 5 Wi-Fi profiles are stored in NVS and the active profile is remembered.
- On boot, the sketch tries the active profile first and then the other saved profiles.
- If you move the extender to a different place, you can switch the active profile from BLE without reflashing.

BLE command format:

- `HELP`
- `LIST`
- `STATUS`
- `SAVE|slot|name|ssid|password|priority|enabled`
- `USE|slot`
- `CONNECT|slot`
- `DEL|slot`
- `ENABLE|slot|0|1`
- `AUTO`

Use `|` as the separator and avoid `|` inside values.
