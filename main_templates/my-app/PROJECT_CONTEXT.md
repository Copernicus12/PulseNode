# PulseNode – Context proiect licență

## Viziune generală
PulseNode este un sistem pentru monitorizare și control energetic pe un prelungitor inteligent cu 3 prize independente.

Fiecare priză are telemetrie separată (curent, putere estimată), iar aplicația web oferă monitorizare live, istoric energetic și control relee.

## Context workspace (actualizat: 12 martie 2026)
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
- Frontend: Blade + Tailwind + JavaScript + componente Vue 3 punctuale (pentru UI/forms interactive).
- Tooling frontend: Vite + plugin Vue + Wayfinder (`resources/js/app.ts` + `resources/js/power-strip-safety-guard.ts` + `resources/js/history-page.ts`).
- UI kit intern: shadcn-vue (folosit gradual; componente active noi: `pagination`, `calendar`, `popover`, `native-select`, plus wrapper reutilizabil `date-picker`).
- Persistență: MySQL/SQLite (în funcție de mediu), cu tabele pentru samples, agregări energetice, profile device și detection plans.
- Store stare live: fișier JSON (`Esp32StateStore`) folosit de endpoint-urile `/api/latest`.

## Flux de date implementat
1. ESP32 trimite date către `POST /api/ingest`.
2. Backend validează payload-ul și actualizează `Esp32StateStore`.
3. Se înregistrează `EnergySample` și agregări zilnice/orare.
4. UI citește live din `/api/latest` (polling global) și actualizează paginile fără refresh.

## Funcționalități implementate (MVP extins)
- Pagini funcționale: `Dashboard`, `Power Strip`, `Devices`, `History`, `Battery`, `Settings`, autentificare.
- Modulul Devices are secțiuni dedicate pe rute separate:
  - `devices.index` (Overview),
  - `devices.profiles.index`,
  - `devices.plans.index`,
  - `devices.activity.index`.
- Control relee: `/api/relay/{relayId}/{state}`.
- Istoric energetic: `/api/energy-history`, `/api/energy-day/{date}`.
- History refactorizat pentru utilizare Home:
  - randare UI prin componentă Vue montată din Blade (`history-page.ts` + `HistoryHomeView.vue`);
  - layout compact, consistent cu `My Devices` (spacing, card rhythm, gradient subtil pe header card);
  - `Hourly load map` cu paginare internă (`4` carduri/pagină);
  - `Socket contribution` mutat sub `Hourly load map` pentru folosirea eficientă a spațiului.
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
- Command palette global în header (`Ctrl/Cmd + K`) pentru search de pagini + quick actions operaționale.

## Actualizări recente (12 martie 2026)
- `PowerStripController` a fost refactorizat pentru secțiunile Devices (overview/profiles/plans/activity), cu metadata per tab și redirecționare contextuală după create/delete/activate.
- Sidebar-ul include acum grupul `My Devices` expandabil + active-state corect pe subrute.
- Pagina `Power Strip` folosește un formular `Safety Guard` montat prin Vue (`#safety-guard-field-root`), cu componente UI reutilizabile (`field`, `select`, `textarea`).
- Politica de guard și notițele sunt persistate în `localStorage`, iar acțiunile folosesc funcțiile JS existente (`saveGuardPolicy`, `simulateGuard`).
- În layout există quick actions suplimentare: navigare rapidă, `Turn all on/off`, `Open raw payload`, `Restart MQTT listener`.
- `vite.config.ts` include explicit plugin-urile Vue + Wayfinder și entrypoint-ul nou pentru Safety Guard.

## Update major pe History (12 martie 2026)
- Selectorul vechi pe săptămâna calendaristică a fost înlocuit cu `Day selector` bazat pe `anchor_date`.
- Fereastra afișată este dinamică: ultimele 7 zile raportate la data ancoră (`window_start` -> `window_end`), fără zile viitoare.
- Navigarea temporală se face prin `DatePicker` reutilizabil (Popover + Calendar), nu prin pagination pe săptămâni.
- După selectarea datei:
  - backend validează și normalizează data ancoră;
  - frontend actualizează query (`anchor_date`, `date`) și reafișează range-ul corespunzător.
- Limite picker:
  - `max_date` = ziua curentă;
  - `min_date` = minimul dintre prima citire reală și o retenție extinsă (până la 5 ani în urmă) pentru navigare istorică.
- Componentizare UI:
  - componentă nouă reutilizabilă `resources/js/components/ui/date-picker/DatePicker.vue`;
  - integrare `calendar` + `popover` + `native-select` din shadcn-vue, cu stil adaptat proiectului.
- Calendar tuning:
  - spațiere pe coloane ajustată pentru lizibilitate mai bună;
  - selecția între zile nu mai „lipește” celulele vizual.

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
