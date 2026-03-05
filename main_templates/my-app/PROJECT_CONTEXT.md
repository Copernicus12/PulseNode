# PulseNode – Context proiect licență

## Viziune generală
PulseNode este un sistem pentru monitorizare și control energetic pe un prelungitor inteligent cu 3 prize independente.

Fiecare priză are telemetrie separată (curent, putere estimată), iar aplicația web oferă monitorizare live, istoric energetic și control relee.

## Context workspace (actualizat: 5 martie 2026)
- Proiectul Laravel principal și activ este `main_templates/my-app`.
- Copiile Laravel redundante au fost eliminate din workspace.
- Orice dezvoltare nouă se face exclusiv în `main_templates/my-app`.

## Arhitectură hardware
- Microcontroler: ESP32-S3 DevKit.
- Senzori curent: ACS712 (1 per priză).
- Număr prize monitorizate: 3.
- Comunicare: MQTT + ingest HTTP către backend.

## Arhitectură software (stare curentă)
- Backend: Laravel 12.
- Frontend: Blade + Tailwind + JavaScript (polling/event-driven pentru live updates).
- Persistență: MySQL/SQLite (în funcție de mediu), cu tabele pentru samples, agregări energetice, profile device și detection plans.
- Store stare live: fișier JSON (`Esp32StateStore`) folosit de endpoint-urile `/api/latest`.

## Flux de date implementat
1. ESP32 trimite date către `POST /api/ingest`.
2. Backend validează payload-ul și actualizează `Esp32StateStore`.
3. Se înregistrează `EnergySample` și agregări zilnice/orare.
4. UI citește live din `/api/latest` (polling global) și actualizează paginile fără refresh.

## Funcționalități implementate (MVP extins)
- Pagini funcționale: `Dashboard`, `Power Strip`, `Devices`, `History`, `Battery`, `Settings`, autentificare.
- Control relee: `/api/relay/{relayId}/{state}`.
- Istoric energetic: `/api/energy-history`, `/api/energy-day/{date}`.
- Dispozitive:
- management profile (create/delete);
- detection plans (create/activate/delete);
- clasificare live per socket prin `DeviceProfiler`.
- Simulator date la rădăcina proiectului: `simulate_socket_data.py` (trimite trafic continuu către ingest).

## Live update UI (fără refresh manual)
- Polling global în layout la 2s pe `/api/latest`.
- Event global browser: `pulsenode:latest`.
- Pagini conectate la event: `Dashboard`, `Power Strip`, `Devices`, `Battery`, `History`.
- Header live telemetry pill (dot online/offline, power, current) actualizat în timp real.

## Update important pe Devices (confidence live)
- Problema rezolvată: bara/textul de `Confidence` nu se actualizau la date noi.
- Soluție:
- endpoint nou `GET /api/devices/live-detections`;
- `devices/index.blade.php` face fetch periodic (throttled) și actualizează:
  - state badge,
  - label/category,
  - confidence text,
  - confidence bar width/color,
  - reason.

## Securitate / runtime
- `POST /api/ingest` suportă token (`X-ESP32-TOKEN`) când este configurat în `.env`.
- Script de simulator citește tokenul din `.env` dacă nu este dat explicit.

## Testare existentă (actualizat)
- `tests/Feature/DevicesManagementTest.php` (management plans/profiles).
- `tests/Feature/EnergyIngestAggregationTest.php` (ingest + agregări).
- `tests/Feature/LiveTelemetryUiTest.php` (polling/event live + endpoint detections).

## Notă de continuitate
- Acest fișier rămâne sursa principală de context proiect.
- Orice schimbare majoră de arhitectură/flux/endpoints trebuie adăugată aici în aceeași zi cu implementarea.
