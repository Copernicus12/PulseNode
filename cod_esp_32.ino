#include <WiFi.h>
#include <PubSubClient.h>
#include <Preferences.h>
#include <NimBLEDevice.h>
#include <math.h>
#include <string.h>
#include <string>
#include <stdio.h>
#include <ctype.h>

// =====================================================
// WIFI + MQTT + BLE PROVISIONING
// =====================================================
const char* FW_VERSION = "esp32-current-refactor-v18-ble-wifi-profiles";

static const uint8_t MAX_WIFI_PROFILES = 5;
static const uint8_t WIFI_PROFILE_NAME_LEN = 24;
static const uint8_t WIFI_SSID_LEN = 32;
static const uint8_t WIFI_PASSWORD_LEN = 64;

struct WifiProfile {
  char name[WIFI_PROFILE_NAME_LEN + 1];
  char ssid[WIFI_SSID_LEN + 1];
  char password[WIFI_PASSWORD_LEN + 1];
  int8_t priority;
  bool enabled;
};

WifiProfile wifiProfiles[MAX_WIFI_PROFILES];
int8_t activeWifiProfile = -1;

Preferences wifiPrefs;

const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 1883;

const char* mqtt_topic_data = "razvy_esp32_2026/data";
const char* mqtt_topic_cmd  = "razvy_esp32_2026/cmd";

WiFiClient espClient;
PubSubClient client(espClient);

static const char* WIFI_PREF_NAMESPACE = "wifi_profiles";
static const uint32_t WIFI_CONNECT_TIMEOUT_MS = 20000;
static const uint32_t WIFI_RETRY_INTERVAL_MS = 30000;
static const uint32_t MQTT_RETRY_INTERVAL_MS = 10000;

static const char* BLE_DEVICE_NAME = "PulseNode-Setup";
static const char* BLE_SERVICE_UUID = "c2c1d340-44d1-4a46-9f26-94b4c26ce001";
static const char* BLE_RX_UUID      = "c2c1d340-44d1-4a46-9f26-94b4c26ce002";
static const char* BLE_TX_UUID      = "c2c1d340-44d1-4a46-9f26-94b4c26ce003";

NimBLEServer* bleServer = nullptr;
NimBLECharacteristic* bleTxCharacteristic = nullptr;
bool bleDeviceConnected = false;
char bleCommandBuffer[256];
size_t bleCommandIndex = 0;

unsigned long lastWifiAttemptMs = 0;
unsigned long lastMqttAttemptMs = 0;
volatile uint8_t lastWifiDisconnectReason = 0;
volatile bool lastWifiDisconnectHasReason = false;

struct WifiScanMatch {
  bool found;
  int channel;
  int8_t rssi;
  uint8_t bssid[6];
};

// =====================================================
// HARDWARE - ESP32-S3
// =====================================================
#define ACS1_PIN    5
#define ACS2_PIN    6
#define ACS3_PIN    4

#define VOLTAGE_PIN 7

#define RELAY1_PIN  15
#define RELAY2_PIN  16
#define RELAY3_PIN  17

#define NUM_CHANNELS 3

// =====================================================
// ADC + SENZORI
// =====================================================
const float ADC_VREF = 3.3f;
const float ADC_MAX  = 4095.0f;

// ACS712 20A => 100mV/A
const float ACS_SENSITIVITY = 0.100f;

// ZMPT - calibrare dupa multimetru
float voltageCalibration = 1000.0f;

// calibrare separata pentru fiecare ACS
float currentCalibration[NUM_CHANNELS] = {3.20f, 3.20f, 3.20f};

// zgomot RMS separat pe fiecare canal
float currentNoiseFloorA[NUM_CHANNELS] = {0.075f, 0.075f, 0.075f};

// offset putere falsa la gol pe fiecare canal
float idlePowerOffsetW[NUM_CHANNELS] = {5.0f, 5.0f, 5.0f};

// inversare semn curent pe fiecare canal
bool invertCurrent[NUM_CHANNELS] = {true, true, true};

// praguri mici doar pentru afisaj/publicare
const float CURRENT_ZERO_THRESHOLD_A = 0.02f;
const float POWER_ZERO_THRESHOLD_W = 1.0f;

// =====================================================
// TIMPI
// =====================================================
const unsigned long SAMPLE_WINDOW_MS    = 1000;
const unsigned long MEASURE_INTERVAL_MS = 1000;
const unsigned long MQTT_INTERVAL_MS    = 2000;
const unsigned long AUTO_RECALIBRATE_AFTER_RELAY_MS = 900;

// =====================================================
// STRUCTURI
// =====================================================
struct SensorChannel {
  uint8_t currentPin;
  uint8_t relayPin;
  float adcOffset;
  bool relayState;
  double energy_kWh;
};

struct VoltageChannel {
  uint8_t pin;
  float adcOffset;
};

struct Measurements {
  float voltageRMS;
  float currentRMS[NUM_CHANNELS];
  float activePowerW[NUM_CHANNELS];
  float apparentPowerVA[NUM_CHANNELS];
  float powerFactor[NUM_CHANNELS];

  float totalActivePowerW;
  float totalApparentPowerVA;
  float totalCurrentRMS;
};

SensorChannel channels[NUM_CHANNELS] = {
  {ACS1_PIN, RELAY1_PIN, 2048.0f, false, 0.0},
  {ACS2_PIN, RELAY2_PIN, 2048.0f, false, 0.0},
  {ACS3_PIN, RELAY3_PIN, 2048.0f, false, 0.0}
};

VoltageChannel zmpt = {VOLTAGE_PIN, 2048.0f};

Measurements meas;
Measurements smoothedMeas;
bool smoothedMeasInitialized = false;

const float TELEMETRY_EMA_ALPHA = 0.45f;

// =====================================================
// STARE
// =====================================================
unsigned long lastMeasureMs = 0;
unsigned long lastMqttMs = 0;
unsigned long lastEnergyMs = 0;
unsigned long relayCalibrationDueMs = 0;
bool relayCalibrationPending = false;

char serialBuffer[64];
uint8_t serialIndex = 0;

// =====================================================
// UTILS
// =====================================================
float averageRaw(uint8_t pin, int samples) {
  uint32_t sum = 0;
  for (int i = 0; i < samples; i++) {
    sum += analogRead(pin);
    delayMicroseconds(120);
  }
  return (float)sum / samples;
}

float clampf(float x, float lo, float hi) {
  if (x < lo) return lo;
  if (x > hi) return hi;
  return x;
}

void toUpperStr(char* s) {
  for (size_t i = 0; i < strlen(s); i++) {
    s[i] = toupper(s[i]);
  }
}

void toLowerStr(char* s) {
  for (size_t i = 0; i < strlen(s); i++) {
    s[i] = tolower(s[i]);
  }
}

void compactJson(char* dst, size_t dstSize, const char* src) {
  size_t j = 0;
  for (size_t i = 0; src[i] != '\0' && j + 1 < dstSize; i++) {
    if (!isspace((unsigned char)src[i])) {
      dst[j++] = tolower((unsigned char)src[i]);
    }
  }
  dst[j] = '\0';
}

float sanitizeCurrent(float current) {
  if (fabs(current) < CURRENT_ZERO_THRESHOLD_A) {
    return 0.0f;
  }
  return current;
}

float sanitizePower(float power) {
  if (fabs(power) < POWER_ZERO_THRESHOLD_W) {
    return 0.0f;
  }
  return power;
}

Measurements smoothMeasurements(const Measurements& raw) {
  if (!smoothedMeasInitialized) {
    smoothedMeas = raw;
    smoothedMeasInitialized = true;
    return smoothedMeas;
  }

  auto smoothValue = [](float previous, float next) -> float {
    return previous + (TELEMETRY_EMA_ALPHA * (next - previous));
  };

  smoothedMeas.voltageRMS = smoothValue(smoothedMeas.voltageRMS, raw.voltageRMS);
  smoothedMeas.totalActivePowerW = smoothValue(smoothedMeas.totalActivePowerW, raw.totalActivePowerW);
  smoothedMeas.totalApparentPowerVA = smoothValue(smoothedMeas.totalApparentPowerVA, raw.totalApparentPowerVA);
  smoothedMeas.totalCurrentRMS = smoothValue(smoothedMeas.totalCurrentRMS, raw.totalCurrentRMS);

  for (int i = 0; i < NUM_CHANNELS; i++) {
    smoothedMeas.currentRMS[i] = smoothValue(smoothedMeas.currentRMS[i], raw.currentRMS[i]);
    smoothedMeas.activePowerW[i] = smoothValue(smoothedMeas.activePowerW[i], raw.activePowerW[i]);
    smoothedMeas.apparentPowerVA[i] = smoothValue(smoothedMeas.apparentPowerVA[i], raw.apparentPowerVA[i]);
    smoothedMeas.powerFactor[i] = smoothValue(smoothedMeas.powerFactor[i], raw.powerFactor[i]);
  }

  return smoothedMeas;
}

const char* wifiStatusToString(wl_status_t status) {
  switch (status) {
    case WL_IDLE_STATUS: return "WL_IDLE_STATUS";
    case WL_NO_SSID_AVAIL: return "WL_NO_SSID_AVAIL";
    case WL_SCAN_COMPLETED: return "WL_SCAN_COMPLETED";
    case WL_CONNECTED: return "WL_CONNECTED";
    case WL_CONNECT_FAILED: return "WL_CONNECT_FAILED";
    case WL_CONNECTION_LOST: return "WL_CONNECTION_LOST";
    case WL_DISCONNECTED: return "WL_DISCONNECTED";
    default: return "WL_UNKNOWN";
  }
}

const char* wifiDisconnectHint(uint8_t reason) {
  switch (reason) {
    case WIFI_REASON_NO_AP_FOUND:
      return "SSID-ul nu este vizibil. Verifica hotspot-ul iPhone, tine telefonul aproape si activeaza Maximize Compatibility.";
    case WIFI_REASON_AUTH_FAIL:
      return "Parola este gresita sau hotspot-ul a refuzat autentificarea.";
    case WIFI_REASON_4WAY_HANDSHAKE_TIMEOUT:
    case WIFI_REASON_HANDSHAKE_TIMEOUT:
      return "Handshake-ul WiFi a expirat. De obicei e semnal slab sau hotspot instabil.";
    case WIFI_REASON_BEACON_TIMEOUT:
      return "ESP32 nu mai primeste beacon-uri. Hotspot-ul s-a intrerupt sau semnalul este prea slab.";
    case WIFI_REASON_ASSOC_FAIL:
      return "Asocierea la AP a esuat. Poate fi o problema de banda/canal sau compatibilitate.";
    case WIFI_REASON_CONNECTION_FAIL:
      return "Conexiunea a esuat generic. Verifica hotspot-ul si incearca din nou.";
    case WIFI_REASON_UNSPECIFIED:
      return "Cauza nespecificata de driver.";
    default:
      return "Nu avem o cauza clara inca. Urmareste statusul WiFi si scan-ul SSID.";
  }
}

// =====================================================
// RELEE
// =====================================================
void setRelay(uint8_t ch, bool on) {
  if (ch >= NUM_CHANNELS) return;

  if (channels[ch].relayState == on) return;

  digitalWrite(channels[ch].relayPin, on ? LOW : HIGH);   // releu activ LOW
  channels[ch].relayState = on;
  relayCalibrationPending = true;
  relayCalibrationDueMs = millis() + AUTO_RECALIBRATE_AFTER_RELAY_MS;
}

void printRelayStatus() {
  for (int i = 0; i < NUM_CHANNELS; i++) {
    Serial.printf("Releu %d: %s\n", i + 1, channels[i].relayState ? "PORNIT" : "OPRIT");
  }
}

// =====================================================
// WIFI + BLE PROVISIONING
// =====================================================
void copyStringField(char* dst, size_t dstSize, const char* src) {
  if (dstSize == 0) return;
  if (src == nullptr) {
    dst[0] = '\0';
    return;
  }
  strncpy(dst, src, dstSize - 1);
  dst[dstSize - 1] = '\0';
}

void clearWifiProfile(uint8_t slot) {
  if (slot >= MAX_WIFI_PROFILES) return;
  memset(&wifiProfiles[slot], 0, sizeof(WifiProfile));
  wifiProfiles[slot].priority = 0;
  wifiProfiles[slot].enabled = false;
}

bool wifiProfileConfigured(uint8_t slot) {
  return slot < MAX_WIFI_PROFILES && wifiProfiles[slot].ssid[0] != '\0';
}

String wifiProfilePrefix(uint8_t slot) {
  return String("p") + String(slot);
}

void persistActiveWifiProfile() {
  wifiPrefs.begin(WIFI_PREF_NAMESPACE, false);
  wifiPrefs.putInt("active", activeWifiProfile);
  wifiPrefs.end();
}

void persistWifiProfile(uint8_t slot) {
  if (slot >= MAX_WIFI_PROFILES) return;

  wifiPrefs.begin(WIFI_PREF_NAMESPACE, false);
  String prefix = wifiProfilePrefix(slot);
  String keyName = prefix + "_name";
  String keySsid = prefix + "_ssid";
  String keyPass = prefix + "_pass";
  String keyPriority = prefix + "_priority";
  String keyEnabled = prefix + "_enabled";

  wifiPrefs.putString(keyName.c_str(), wifiProfiles[slot].name);
  wifiPrefs.putString(keySsid.c_str(), wifiProfiles[slot].ssid);
  wifiPrefs.putString(keyPass.c_str(), wifiProfiles[slot].password);
  wifiPrefs.putInt(keyPriority.c_str(), wifiProfiles[slot].priority);
  wifiPrefs.putBool(keyEnabled.c_str(), wifiProfiles[slot].enabled);

  wifiPrefs.end();
}

void eraseWifiProfile(uint8_t slot) {
  if (slot >= MAX_WIFI_PROFILES) return;

  wifiPrefs.begin(WIFI_PREF_NAMESPACE, false);
  String prefix = wifiProfilePrefix(slot);
  String keyName = prefix + "_name";
  String keySsid = prefix + "_ssid";
  String keyPass = prefix + "_pass";
  String keyPriority = prefix + "_priority";
  String keyEnabled = prefix + "_enabled";

  wifiPrefs.remove(keyName.c_str());
  wifiPrefs.remove(keySsid.c_str());
  wifiPrefs.remove(keyPass.c_str());
  wifiPrefs.remove(keyPriority.c_str());
  wifiPrefs.remove(keyEnabled.c_str());

  wifiPrefs.end();
}

void loadWifiProfiles() {
  wifiPrefs.begin(WIFI_PREF_NAMESPACE, true);
  activeWifiProfile = wifiPrefs.getInt("active", -1);

  for (uint8_t slot = 0; slot < MAX_WIFI_PROFILES; slot++) {
    clearWifiProfile(slot);
    String prefix = wifiProfilePrefix(slot);
    String keyName = prefix + "_name";
    String keySsid = prefix + "_ssid";
    String keyPass = prefix + "_pass";
    String keyPriority = prefix + "_priority";
    String keyEnabled = prefix + "_enabled";

    String name = wifiPrefs.getString(keyName.c_str(), "");
    String ssidValue = wifiPrefs.getString(keySsid.c_str(), "");
    String passValue = wifiPrefs.getString(keyPass.c_str(), "");
    int8_t priority = (int8_t)wifiPrefs.getInt(keyPriority.c_str(), 0);
    bool enabled = wifiPrefs.getBool(keyEnabled.c_str(), false);

    copyStringField(wifiProfiles[slot].name, sizeof(wifiProfiles[slot].name), name.c_str());
    copyStringField(wifiProfiles[slot].ssid, sizeof(wifiProfiles[slot].ssid), ssidValue.c_str());
    copyStringField(wifiProfiles[slot].password, sizeof(wifiProfiles[slot].password), passValue.c_str());
    wifiProfiles[slot].priority = priority;
    wifiProfiles[slot].enabled = enabled;
  }

  wifiPrefs.end();

  if (activeWifiProfile < 0 || activeWifiProfile >= MAX_WIFI_PROFILES || !wifiProfileConfigured((uint8_t)activeWifiProfile)) {
    activeWifiProfile = -1;
  }
}

void printWifiProfiles() {
  Serial.println("============== WIFI PROFILES ==============");
  Serial.printf("Active profile: %d\n", activeWifiProfile);

  for (uint8_t slot = 0; slot < MAX_WIFI_PROFILES; slot++) {
    Serial.printf("Slot %d | %s | SSID=%s | enabled=%s | priority=%d\n",
                  slot,
                  wifiProfiles[slot].name[0] ? wifiProfiles[slot].name : "(empty)",
                  wifiProfiles[slot].ssid[0] ? wifiProfiles[slot].ssid : "-",
                  wifiProfiles[slot].enabled ? "true" : "false",
                  wifiProfiles[slot].priority);
  }

  Serial.println("===========================================");
}

void printWifiScanResults() {
  Serial.println("============== WIFI SCAN ==============");

  int scanCount = WiFi.scanNetworks(false, true);
  if (scanCount < 0) {
    delay(300);
    scanCount = WiFi.scanNetworks(false, true);
  }
  if (scanCount < 0) {
    Serial.println("Scan WiFi: nu a putut fi rulat.");
    Serial.println("=======================================");
    return;
  }

  Serial.printf("Retele gasite: %d\n", scanCount);
  if (scanCount == 0) {
    Serial.println("(nicio retea detectata)");
  }

  for (int i = 0; i < scanCount; i++) {
    Serial.printf("%2d | SSID=%s | RSSI=%d dBm | CH=%d\n",
                  i + 1,
                  WiFi.SSID(i).c_str(),
                  WiFi.RSSI(i),
                  WiFi.channel(i));
  }

  WiFi.scanDelete();
  Serial.println("=======================================");
}

void resetWifiStack() {
  WiFi.disconnect(false, false);
  delay(200);
  WiFi.mode(WIFI_OFF);
  delay(500);
  WiFi.mode(WIFI_STA);
  delay(300);
  WiFi.setAutoReconnect(true);
  WiFi.persistent(false);
  WiFi.setSleep(false);
}

int scanWifiNetworksWithRetry() {
  int scanCount = WiFi.scanNetworks(false, true);
  if (scanCount >= 0) {
    return scanCount;
  }

  delay(300);
  scanCount = WiFi.scanNetworks(false, true);
  return scanCount;
}

void onWifiEvent(WiFiEvent_t event, WiFiEventInfo_t info) {
  switch (event) {
    case ARDUINO_EVENT_WIFI_STA_START:
      Serial.println("WiFi event: STA_START");
      break;
    case ARDUINO_EVENT_WIFI_STA_CONNECTED:
      Serial.println("WiFi event: STA_CONNECTED");
      break;
    case ARDUINO_EVENT_WIFI_STA_GOT_IP:
      Serial.print("WiFi event: STA_GOT_IP ");
      Serial.println(WiFi.localIP());
      break;
    case ARDUINO_EVENT_WIFI_STA_DISCONNECTED: {
      uint8_t reason = info.wifi_sta_disconnected.reason;
      if (reason == 0) {
        reason = WIFI_REASON_UNSPECIFIED;
      }
      lastWifiDisconnectReason = reason;
      lastWifiDisconnectHasReason = true;
      Serial.printf("WiFi event: STA_DISCONNECTED reason=%u (%s)\n",
                    reason,
                    WiFi.STA.disconnectReasonName((wifi_err_reason_t)reason));
      Serial.printf("Hint: %s\n", wifiDisconnectHint(reason));
      break;
    }
    default:
      break;
  }
}

void sendBleLine(const String& line) {
  Serial.println(line);

  if (bleDeviceConnected && bleTxCharacteristic != nullptr) {
    bleTxCharacteristic->setValue(line.c_str());
    bleTxCharacteristic->notify();
  }
}

void sendBleHelp() {
  sendBleLine("OK|COMMANDS");
  sendBleLine("LIST|slot|name|ssid|password|enabled|priority");
  sendBleLine("STATUS");
  sendBleLine("SAVE|slot|name|ssid|password|priority|enabled");
  sendBleLine("USE|slot");
  sendBleLine("CONNECT|slot");
  sendBleLine("DEL|slot");
  sendBleLine("ENABLE|slot|0|1");
  sendBleLine("ACTIVE|slot");
  sendBleLine("AUTO");
  sendBleLine("NOTE|Use | as separator. Avoid | inside values.");
}

void bleAppendCommandBytes(const uint8_t* data, size_t length);

void trimWhitespace(char* text) {
  if (text == nullptr) return;

  char* start = text;
  while (*start && isspace((unsigned char)*start)) start++;

  char* end = start + strlen(start);
  while (end > start && isspace((unsigned char)*(end - 1))) end--;

  size_t newLen = (size_t)(end - start);
  memmove(text, start, newLen);
  text[newLen] = '\0';
}

bool parseBoolToken(const char* text, bool defaultValue) {
  if (text == nullptr || text[0] == '\0') return defaultValue;
  if (strcmp(text, "1") == 0 || strcmp(text, "true") == 0 || strcmp(text, "TRUE") == 0 || strcmp(text, "on") == 0 || strcmp(text, "ON") == 0) {
    return true;
  }
  if (strcmp(text, "0") == 0 || strcmp(text, "false") == 0 || strcmp(text, "FALSE") == 0 || strcmp(text, "off") == 0 || strcmp(text, "OFF") == 0) {
    return false;
  }
  return defaultValue;
}

bool parseIntToken(const char* text, int& valueOut) {
  if (text == nullptr || text[0] == '\0') return false;
  char* endPtr = nullptr;
  long parsed = strtol(text, &endPtr, 10);
  if (endPtr == text) return false;
  valueOut = (int)parsed;
  return true;
}

bool isProfileVisibleOnScan(uint8_t slot, int scanCount) {
  if (!wifiProfileConfigured(slot) || scanCount <= 0) return false;

  for (int i = 0; i < scanCount; i++) {
    String visible = WiFi.SSID(i);
    if (visible.equals(wifiProfiles[slot].ssid)) {
      return true;
    }
  }

  return false;
}

WifiScanMatch findBestScanMatchForProfile(uint8_t slot, int scanCount) {
  WifiScanMatch match = {false, 0, -127, {0, 0, 0, 0, 0, 0}};

  if (!wifiProfileConfigured(slot) || scanCount <= 0) {
    return match;
  }

  for (int i = 0; i < scanCount; i++) {
    String visible = WiFi.SSID(i);
    if (!visible.equals(wifiProfiles[slot].ssid)) {
      continue;
    }

    int8_t rssi = (int8_t)WiFi.RSSI(i);
    if (!match.found || rssi > match.rssi) {
      match.found = true;
      match.channel = WiFi.channel(i);
      match.rssi = rssi;

      const uint8_t* bssid = WiFi.BSSID(i);
      if (bssid != nullptr) {
        memcpy(match.bssid, bssid, sizeof(match.bssid));
      }
    }
  }

  return match;
}

int chooseProfileOrder(uint8_t* order, int maxOrder) {
  bool used[MAX_WIFI_PROFILES] = {false, false, false, false, false};
  bool visible[MAX_WIFI_PROFILES] = {false, false, false, false, false};
  bool anyVisible = false;

  int scanCount = scanWifiNetworksWithRetry();
  if (scanCount > 0) {
    for (uint8_t slot = 0; slot < MAX_WIFI_PROFILES; slot++) {
      visible[slot] = isProfileVisibleOnScan(slot, scanCount);
      if (visible[slot]) {
        anyVisible = true;
      }
    }
  }
  WiFi.scanDelete();

  int count = 0;
  auto pickBest = [&](bool requireVisible) -> int {
    int bestSlot = -1;
    int bestScore = -32768;

    for (uint8_t slot = 0; slot < MAX_WIFI_PROFILES; slot++) {
      if (used[slot] || !wifiProfiles[slot].enabled || !wifiProfileConfigured(slot)) continue;
      if (requireVisible && !visible[slot]) continue;

      int score = wifiProfiles[slot].priority * 10;
      if (slot == activeWifiProfile) score += 1000;
      if (visible[slot]) score += 500;
      score += (MAX_WIFI_PROFILES - slot);

      if (score > bestScore) {
        bestScore = score;
        bestSlot = slot;
      }
    }

    return bestSlot;
  };

  if (activeWifiProfile >= 0 && activeWifiProfile < MAX_WIFI_PROFILES &&
      wifiProfiles[activeWifiProfile].enabled && wifiProfileConfigured(activeWifiProfile) &&
      (visible[activeWifiProfile] || !anyVisible)) {
    order[count++] = (uint8_t)activeWifiProfile;
    used[activeWifiProfile] = true;
  }

  while (count < maxOrder) {
    int bestSlot = pickBest(false);
    if (bestSlot < 0) break;
    used[bestSlot] = true;
    order[count++] = (uint8_t)bestSlot;
  }

  return count;
}

bool connectToWifiProfile(uint8_t slot, uint32_t timeoutMs) {
  if (slot >= MAX_WIFI_PROFILES) return false;
  if (!wifiProfiles[slot].enabled || !wifiProfileConfigured(slot)) return false;
  if (WiFi.status() == WL_CONNECTED) {
    return true;
  }

  Serial.printf("Conectare WiFi prin profilul %d (%s)...\n",
                slot,
                wifiProfiles[slot].name[0] ? wifiProfiles[slot].name : wifiProfiles[slot].ssid);
  Serial.printf("WiFi status initial: %s (%d)\n",
                wifiStatusToString(WiFi.status()),
                (int)WiFi.status());

  lastWifiDisconnectHasReason = false;

  resetWifiStack();

  WiFi.begin(wifiProfiles[slot].ssid, wifiProfiles[slot].password);

  unsigned long started = millis();
  unsigned long lastRetryMs = started;
  bool scanAttempted = false;
  while (millis() - started < timeoutMs) {
    if (WiFi.status() == WL_CONNECTED) {
      activeWifiProfile = (int8_t)slot;
      persistActiveWifiProfile();
      lastMqttAttemptMs = millis() - MQTT_RETRY_INTERVAL_MS;
      Serial.print("Conectat la WiFi, IP: ");
      Serial.println(WiFi.localIP());
      return true;
    }

    if (!scanAttempted && millis() - started >= 2500) {
      scanAttempted = true;

      int scanCount = scanWifiNetworksWithRetry();
      WifiScanMatch scanMatch = findBestScanMatchForProfile(slot, scanCount);
      if (scanCount < 0) {
        Serial.println("Scan WiFi: nu a putut fi rulat.");
      } else if (scanMatch.found) {
        Serial.printf("SSID '%s' este VIZIBIL in scan pe CH=%d, RSSI=%d dBm.\n",
                      wifiProfiles[slot].ssid,
                      scanMatch.channel,
                      scanMatch.rssi);
        Serial.print("Incerc conectarea pe BSSID=");
        for (size_t i = 0; i < sizeof(scanMatch.bssid); i++) {
          if (i > 0) Serial.print(":");
          Serial.printf("%02X", scanMatch.bssid[i]);
        }
        Serial.println();
        WiFi.disconnect(false, false);
        delay(150);
        WiFi.begin(wifiProfiles[slot].ssid, wifiProfiles[slot].password, scanMatch.channel, scanMatch.bssid, true);
      } else {
        bool visible = isProfileVisibleOnScan(slot, scanCount);
        Serial.printf("SSID '%s' este %s in scan.\n",
                      wifiProfiles[slot].ssid,
                      visible ? "VIZIBIL" : "NEVIZIBIL");
      }
      WiFi.scanDelete();
    }

    if (millis() - lastRetryMs >= 6000) {
      Serial.println("Retry WiFi.begin()...");
      WiFi.disconnect(false, false);
      delay(100);
      WiFi.begin(wifiProfiles[slot].ssid, wifiProfiles[slot].password);
      lastRetryMs = millis();
    }

    delay(250);
  }

  Serial.printf("Esec la conectarea profilului %d\n", slot);
  wl_status_t status = WiFi.status();
  Serial.printf("WiFi status final: %s (%d)\n",
                wifiStatusToString(status),
                (int)status);
  if (lastWifiDisconnectHasReason) {
    Serial.printf("Ultimul motiv de deconectare: %u (%s)\n",
                  lastWifiDisconnectReason,
                  WiFi.STA.disconnectReasonName((wifi_err_reason_t)lastWifiDisconnectReason));
    Serial.printf("Hint: %s\n", wifiDisconnectHint(lastWifiDisconnectReason));
  } else {
    Serial.println("Nu am primit inca un motiv de deconectare de la driver.");
  }
  return false;
}

bool connectBestSavedWifi() {
  uint8_t order[MAX_WIFI_PROFILES] = {0, 0, 0, 0, 0};
  int orderCount = chooseProfileOrder(order, MAX_WIFI_PROFILES);

  for (int i = 0; i < orderCount; i++) {
    if (connectToWifiProfile(order[i], WIFI_CONNECT_TIMEOUT_MS)) {
      return true;
    }
  }

  Serial.println("Niciun profil WiFi nu a putut fi conectat.");
  return false;
}

void saveProfileFromTokens(int slot, const char* name, const char* ssid, const char* password, int priority, bool enabled) {
  if (slot < 0 || slot >= MAX_WIFI_PROFILES) {
    sendBleLine("ERR|slot invalid");
    return;
  }

  clearWifiProfile((uint8_t)slot);
  copyStringField(wifiProfiles[slot].name, sizeof(wifiProfiles[slot].name), name);
  copyStringField(wifiProfiles[slot].ssid, sizeof(wifiProfiles[slot].ssid), ssid);
  copyStringField(wifiProfiles[slot].password, sizeof(wifiProfiles[slot].password), password);
  wifiProfiles[slot].priority = (int8_t)priority;
  wifiProfiles[slot].enabled = enabled;

  persistWifiProfile((uint8_t)slot);

  if (activeWifiProfile < 0 && enabled) {
    activeWifiProfile = (int8_t)slot;
    persistActiveWifiProfile();
  }

  sendBleLine(String("OK|SAVED|") + slot + "|" + wifiProfiles[slot].name + "|" + wifiProfiles[slot].ssid);
  if (enabled) {
    if (connectToWifiProfile((uint8_t)slot, WIFI_CONNECT_TIMEOUT_MS)) {
      sendBleLine(String("OK|CONNECTED|") + slot);
    } else {
      sendBleLine(String("WARN|SAVED_BUT_CONNECT_FAILED|") + slot);
    }
  }
}

void handleBleCommandLine(char* line) {
  trimWhitespace(line);
  if (line[0] == '\0') return;

  char* savePtr = nullptr;
  char* command = strtok_r(line, "|", &savePtr);
  if (command == nullptr) return;

  trimWhitespace(command);
  toUpperStr(command);

  if (strcmp(command, "HELP") == 0) {
    sendBleHelp();
    return;
  }

  if (strcmp(command, "LIST") == 0) {
    sendBleLine(String("OK|ACTIVE|") + activeWifiProfile);
    for (uint8_t slot = 0; slot < MAX_WIFI_PROFILES; slot++) {
      String item = String("PROFILE|") + slot + "|" +
                    (wifiProfiles[slot].name[0] ? wifiProfiles[slot].name : "-") + "|" +
                    (wifiProfiles[slot].ssid[0] ? wifiProfiles[slot].ssid : "-") + "|" +
                    (wifiProfiles[slot].password[0] ? wifiProfiles[slot].password : "-") + "|" +
                    (wifiProfiles[slot].enabled ? "1" : "0") + "|" +
                    String(wifiProfiles[slot].priority);
      sendBleLine(item);
    }
    return;
  }

  if (strcmp(command, "STATUS") == 0) {
    String wifiState = (WiFi.status() == WL_CONNECTED) ? "connected" : "disconnected";
    String payload = String("OK|STATUS|wifi=") + wifiState +
                     "|ip=" + WiFi.localIP().toString() +
                     "|ssid=" + WiFi.SSID() +
                     "|active=" + activeWifiProfile;
    sendBleLine(payload);
    return;
  }

  if (strcmp(command, "ACTIVE") == 0 || strcmp(command, "USE") == 0 || strcmp(command, "CONNECT") == 0) {
    char* slotText = strtok_r(nullptr, "|", &savePtr);
    int slot = -1;
    if (!parseIntToken(slotText, slot)) {
      sendBleLine("ERR|missing slot");
      return;
    }

    if (slot < 0 || slot >= MAX_WIFI_PROFILES || !wifiProfileConfigured((uint8_t)slot)) {
      sendBleLine("ERR|profile missing");
      return;
    }

    activeWifiProfile = (int8_t)slot;
    persistActiveWifiProfile();
    if (connectToWifiProfile((uint8_t)slot, WIFI_CONNECT_TIMEOUT_MS)) {
      sendBleLine(String("OK|CONNECTED|") + slot);
    } else {
      sendBleLine(String("ERR|CONNECT_FAILED|") + slot);
    }
    return;
  }

  if (strcmp(command, "DEL") == 0) {
    char* slotText = strtok_r(nullptr, "|", &savePtr);
    int slot = -1;
    if (!parseIntToken(slotText, slot) || slot < 0 || slot >= MAX_WIFI_PROFILES) {
      sendBleLine("ERR|missing slot");
      return;
    }

    eraseWifiProfile((uint8_t)slot);
    clearWifiProfile((uint8_t)slot);
    if (activeWifiProfile == slot) {
      activeWifiProfile = -1;
      persistActiveWifiProfile();
    }
    sendBleLine(String("OK|DELETED|") + slot);
    return;
  }

  if (strcmp(command, "ENABLE") == 0) {
    char* slotText = strtok_r(nullptr, "|", &savePtr);
    char* stateText = strtok_r(nullptr, "|", &savePtr);
    int slot = -1;
    if (!parseIntToken(slotText, slot) || slot < 0 || slot >= MAX_WIFI_PROFILES) {
      sendBleLine("ERR|missing slot");
      return;
    }

    wifiProfiles[slot].enabled = parseBoolToken(stateText, true);
    persistWifiProfile((uint8_t)slot);
    sendBleLine(String("OK|ENABLED|") + slot + "|" + (wifiProfiles[slot].enabled ? "1" : "0"));
    return;
  }

  if (strcmp(command, "SAVE") == 0) {
    char* slotText = strtok_r(nullptr, "|", &savePtr);
    char* nameText = strtok_r(nullptr, "|", &savePtr);
    char* ssidText = strtok_r(nullptr, "|", &savePtr);
    char* passText = strtok_r(nullptr, "|", &savePtr);
    char* priorityText = strtok_r(nullptr, "|", &savePtr);
    char* enabledText = strtok_r(nullptr, "|", &savePtr);

    int slot = -1;
    int priority = 0;
    if (!parseIntToken(slotText, slot)) {
      sendBleLine("ERR|bad slot or priority");
      return;
    }

    if (nameText == nullptr || ssidText == nullptr || passText == nullptr) {
      sendBleLine("ERR|missing name ssid password");
      return;
    }

    if (priorityText != nullptr && priorityText[0] != '\0') {
      parseIntToken(priorityText, priority);
    }

    bool enabled = parseBoolToken(enabledText, true);
    saveProfileFromTokens(slot, nameText ? nameText : "", ssidText ? ssidText : "", passText ? passText : "", priority, enabled);
    return;
  }

  if (strcmp(command, "CONNECTBEST") == 0 || strcmp(command, "AUTO") == 0) {
    if (connectBestSavedWifi()) {
      sendBleLine("OK|CONNECTED_BEST");
    } else {
      sendBleLine("ERR|NO_WIFI_PROFILE_CONNECTED");
    }
    return;
  }

  sendBleLine(String("ERR|UNKNOWN|") + command);
}

void processBleCommandBuffer() {
  bleCommandBuffer[bleCommandIndex] = '\0';

  char* start = bleCommandBuffer;
  while (*start != '\0') {
    char* newline = strpbrk(start, "\r\n");
    if (newline == nullptr) {
      char lineCopy[sizeof(bleCommandBuffer)];
      copyStringField(lineCopy, sizeof(lineCopy), start);
      handleBleCommandLine(lineCopy);
      break;
    }

    *newline = '\0';
    if (*start != '\0') {
      char lineCopy[sizeof(bleCommandBuffer)];
      copyStringField(lineCopy, sizeof(lineCopy), start);
      handleBleCommandLine(lineCopy);
    }

    start = newline + 1;
  }

  bleCommandIndex = 0;
  bleCommandBuffer[0] = '\0';
}

void bleAppendCommandBytes(const uint8_t* data, size_t length) {
  bool sawLineBreak = false;

  for (size_t i = 0; i < length; i++) {
    char c = (char)data[i];
    if (c == '\0') continue;

    if (bleCommandIndex < sizeof(bleCommandBuffer) - 1) {
      bleCommandBuffer[bleCommandIndex++] = c;
    }

    if (c == '\n' || c == '\r') {
      sawLineBreak = true;
      processBleCommandBuffer();
    }
  }

  if (!sawLineBreak && length > 0) {
    processBleCommandBuffer();
  }
}

class BleServerCallbacks : public NimBLEServerCallbacks {
  void onConnect(NimBLEServer* pServer, NimBLEConnInfo& connInfo) override {
    (void)pServer;
    (void)connInfo;
    bleDeviceConnected = true;
    Serial.println("BLE client connected.");
  }

  void onDisconnect(NimBLEServer* pServer, NimBLEConnInfo& connInfo, int reason) override {
    (void)pServer;
    (void)connInfo;
    (void)reason;
    bleDeviceConnected = false;
    Serial.println("BLE client disconnected, restarting advertising.");
    NimBLEDevice::startAdvertising();
  }
};

class BleRxCallbacks : public NimBLECharacteristicCallbacks {
  void onWrite(NimBLECharacteristic* characteristic, NimBLEConnInfo& connInfo) override {
    (void)connInfo;
    std::string value = characteristic->getValue();
    if (!value.empty()) {
      bleAppendCommandBytes((const uint8_t*)value.data(), value.size());
    }
  }
};

void startBleProvisioning() {
  NimBLEDevice::init(BLE_DEVICE_NAME);
  NimBLEDevice::setPower(ESP_PWR_LVL_P9);

  bleServer = NimBLEDevice::createServer();
  bleServer->setCallbacks(new BleServerCallbacks());

  NimBLEService* service = bleServer->createService(BLE_SERVICE_UUID);

  bleTxCharacteristic = service->createCharacteristic(
    BLE_TX_UUID,
    NIMBLE_PROPERTY::NOTIFY
  );

  NimBLECharacteristic* rxCharacteristic = service->createCharacteristic(
    BLE_RX_UUID,
    NIMBLE_PROPERTY::WRITE | NIMBLE_PROPERTY::WRITE_NR
  );
  rxCharacteristic->setCallbacks(new BleRxCallbacks());

  service->start();

  NimBLEAdvertising* advertising = NimBLEDevice::getAdvertising();
  advertising->setName(BLE_DEVICE_NAME);
  advertising->addServiceUUID(BLE_SERVICE_UUID);
  advertising->enableScanResponse(true);
  NimBLEDevice::startAdvertising();

  Serial.println("BLE provisioning pornit. Foloseste un client BLE si trimite HELP.");
}

void setup_wifi() {
  delay(100);

  resetWifiStack();
  WiFi.onEvent(onWifiEvent);

  loadWifiProfiles();
  printWifiProfiles();
  startBleProvisioning();

  if (!connectBestSavedWifi()) {
    Serial.println("Raman in BLE provisioning pana setezi un profil valid.");
  }

  lastWifiAttemptMs = millis();
}

// =====================================================
// CALIBRARE OFFSET
// =====================================================
void recalibrateSensors() {
  Serial.println("Calibrare offset senzori... fara consumatori conectati / porniti!");

  for (int i = 0; i < NUM_CHANNELS; i++) {
    channels[i].adcOffset = averageRaw(channels[i].currentPin, 5000);
    Serial.printf("ACS%d offset ADC = %.2f\n", i + 1, channels[i].adcOffset);
  }

  zmpt.adcOffset = averageRaw(zmpt.pin, 5000);
  Serial.printf("ZMPT offset ADC = %.2f\n", zmpt.adcOffset);
}

// =====================================================
// MASURARE PUTERE - 3 CANALE
// =====================================================
Measurements measurePower() {
  Measurements m;

  m.voltageRMS = 0.0f;
  m.totalActivePowerW = 0.0f;
  m.totalApparentPowerVA = 0.0f;
  m.totalCurrentRMS = 0.0f;

  for (int i = 0; i < NUM_CHANNELS; i++) {
    m.currentRMS[i] = 0.0f;
    m.activePowerW[i] = 0.0f;
    m.apparentPowerVA[i] = 0.0f;
    m.powerFactor[i] = 0.0f;
  }

  unsigned long start = millis();

  double sumV2 = 0.0;
  double sumI2[NUM_CHANNELS] = {0.0, 0.0, 0.0};
  double sumP[NUM_CHANNELS]  = {0.0, 0.0, 0.0};
  uint32_t count = 0;

  int minI[NUM_CHANNELS] = {4095, 4095, 4095};
  int maxI[NUM_CHANNELS] = {0, 0, 0};
  int minV = 4095, maxV = 0;

  while (millis() - start < SAMPLE_WINDOW_MS) {
    int rawV = analogRead(zmpt.pin);
    if (rawV < minV) minV = rawV;
    if (rawV > maxV) maxV = rawV;

    float centeredV = (float)rawV - zmpt.adcOffset;
    float vAdcV = centeredV * (ADC_VREF / ADC_MAX);
    float voltage = vAdcV * voltageCalibration;

    sumV2 += (double)voltage * (double)voltage;

    for (int i = 0; i < NUM_CHANNELS; i++) {
      int rawI = analogRead(channels[i].currentPin);

      if (rawI < minI[i]) minI[i] = rawI;
      if (rawI > maxI[i]) maxI[i] = rawI;

      float centeredI = (float)rawI - channels[i].adcOffset;
      float vAdcI = centeredI * (ADC_VREF / ADC_MAX);

      float current = (vAdcI / ACS_SENSITIVITY) * currentCalibration[i];
      if (invertCurrent[i]) current = -current;

      sumI2[i] += (double)current * (double)current;
      sumP[i]  += (double)voltage * (double)current;
    }

    count++;
    delayMicroseconds(150);
  }

  if (count == 0) return m;

  m.voltageRMS = sqrt(sumV2 / (double)count);

  for (int i = 0; i < NUM_CHANNELS; i++) {
    m.currentRMS[i] = sqrt(sumI2[i] / (double)count);
    m.activePowerW[i] = sumP[i] / (double)count;

    float i2corr = m.currentRMS[i] * m.currentRMS[i] - currentNoiseFloorA[i] * currentNoiseFloorA[i];
    if (i2corr < 0.0f) i2corr = 0.0f;
    m.currentRMS[i] = sqrt(i2corr);

    if (m.activePowerW[i] > 0.0f) {
      m.activePowerW[i] -= idlePowerOffsetW[i];
      if (m.activePowerW[i] < 0.0f) m.activePowerW[i] = 0.0f;
    } else {
      m.activePowerW[i] += idlePowerOffsetW[i];
      if (m.activePowerW[i] > 0.0f) m.activePowerW[i] = 0.0f;
    }

    if (m.activePowerW[i] < 0.0f) {
      m.activePowerW[i] = 0.0f;
    }

    m.apparentPowerVA[i] = m.voltageRMS * m.currentRMS[i];

    if (m.apparentPowerVA[i] > 0.5f) {
      m.powerFactor[i] = m.activePowerW[i] / m.apparentPowerVA[i];
    } else {
      m.powerFactor[i] = 0.0f;
    }

    m.powerFactor[i] = clampf(m.powerFactor[i], -1.0f, 1.0f);

    m.currentRMS[i] = sanitizeCurrent(m.currentRMS[i]);
    m.activePowerW[i] = sanitizePower(m.activePowerW[i]);
    if (fabs(m.apparentPowerVA[i]) < 1.0f) m.apparentPowerVA[i] = 0.0f;
    if (m.currentRMS[i] == 0.0f) m.powerFactor[i] = 0.0f;

    m.totalCurrentRMS += m.currentRMS[i];
    m.totalActivePowerW += m.activePowerW[i];
    m.totalApparentPowerVA += m.apparentPowerVA[i];

    Serial.printf("DEBUG ACS%d | I[min=%d max=%d off=%.1f]\n",
                  i + 1, minI[i], maxI[i], channels[i].adcOffset);
  }

  Serial.printf("DEBUG ZMPT | V[min=%d max=%d off=%.1f]\n", minV, maxV, zmpt.adcOffset);

  return m;
}

// =====================================================
// STATUS
// =====================================================
void printStatus() {
  Serial.println("============== STATUS ==============");
  Serial.printf("Tensiune RMS: %.1f V\n", meas.voltageRMS);

  for (int i = 0; i < NUM_CHANNELS; i++) {
    Serial.printf("CH%d | Relay=%s | I=%.3f A | P=%.1f W | S=%.1f VA | PF=%.2f | E=%.5f kWh\n",
                  i + 1,
                  channels[i].relayState ? "ON" : "OFF",
                  meas.currentRMS[i],
                  meas.activePowerW[i],
                  meas.apparentPowerVA[i],
                  meas.powerFactor[i],
                  channels[i].energy_kWh);
  }

  Serial.printf("TOTAL | I=%.3f A | P=%.1f W | S=%.1f VA\n",
                meas.totalCurrentRMS,
                meas.totalActivePowerW,
                meas.totalApparentPowerVA);
  Serial.println("====================================");
}

// =====================================================
// COMENZI
// =====================================================
void processCommand(const char* cmd) {
  if (strcmp(cmd, "ON1") == 0) {
    setRelay(0, true);
    Serial.println("Releu 1: PORNIT");
  }
  else if (strcmp(cmd, "OFF1") == 0) {
    setRelay(0, false);
    Serial.println("Releu 1: OPRIT");
  }
  else if (strcmp(cmd, "ON2") == 0) {
    setRelay(1, true);
    Serial.println("Releu 2: PORNIT");
  }
  else if (strcmp(cmd, "OFF2") == 0) {
    setRelay(1, false);
    Serial.println("Releu 2: OPRIT");
  }
  else if (strcmp(cmd, "ON3") == 0) {
    setRelay(2, true);
    Serial.println("Releu 3: PORNIT");
  }
  else if (strcmp(cmd, "OFF3") == 0) {
    setRelay(2, false);
    Serial.println("Releu 3: OPRIT");
  }
  else if (strcmp(cmd, "ALL_ON") == 0) {
    for (int i = 0; i < NUM_CHANNELS; i++) setRelay(i, true);
    Serial.println("Toate releele: PORNITE");
  }
  else if (strcmp(cmd, "ALL_OFF") == 0) {
    for (int i = 0; i < NUM_CHANNELS; i++) setRelay(i, false);
    Serial.println("Toate releele: OPRITE");
  }
  else if (strcmp(cmd, "STATUS") == 0) {
    printStatus();
  }
  else if (strcmp(cmd, "LIST") == 0) {
    printWifiProfiles();
  }
  else if (strcmp(cmd, "SCAN") == 0) {
    printWifiScanResults();
  }
  else if (strcmp(cmd, "CAL") == 0) {
    recalibrateSensors();
  }
  else if (strcmp(cmd, "RESET_ENERGY") == 0) {
    for (int i = 0; i < NUM_CHANNELS; i++) {
      channels[i].energy_kWh = 0.0;
    }
    Serial.println("Energia a fost resetata pe toate canalele.");
  }
  else if (strcmp(cmd, "HELP") == 0) {
    Serial.println("Comenzi:");
    Serial.println("ON1 OFF1 ON2 OFF2 ON3 OFF3");
    Serial.println("ALL_ON ALL_OFF");
    Serial.println("STATUS LIST SCAN CAL RESET_ENERGY HELP");
    Serial.println("BLE provisioning: HELP LIST SAVE|slot|name|ssid|password|priority|enabled USE|slot DEL|slot ENABLE|slot|0|1");
  }
  else if (strlen(cmd) > 0) {
    Serial.print("Comanda necunoscuta: ");
    Serial.println(cmd);
  }
}

// =====================================================
// MQTT PARSE
// compatibil si cu dashboard-ul tau:
// {"relay_1":true,"relay_2":false,"relay_3":true}
// {"relay1":"on"}
// {"relay2":"off"}
// {"relay3":"on"}
// {"all":"off"}
// =====================================================
void parseMqttJson(const char* msg) {
  char normalized[160];
  compactJson(normalized, sizeof(normalized), msg);

  if (strstr(normalized, "\"relay_1\":true") ||
      strstr(normalized, "\"relay_1\":\"true\"") ||
      strstr(normalized, "\"relay_1\":1") ||
      strstr(normalized, "\"relay1\":\"on\"") ||
      strstr(normalized, "\"relay\":1,\"state\":\"on\"") ||
      strstr(normalized, "\"relay\":\"1\",\"state\":\"on\"")) {
    setRelay(0, true);
  } else if (strstr(normalized, "\"relay_1\":false") ||
             strstr(normalized, "\"relay_1\":\"false\"") ||
             strstr(normalized, "\"relay_1\":0") ||
             strstr(normalized, "\"relay1\":\"off\"") ||
             strstr(normalized, "\"relay\":1,\"state\":\"off\"") ||
             strstr(normalized, "\"relay\":\"1\",\"state\":\"off\"")) {
    setRelay(0, false);
  }

  if (strstr(normalized, "\"relay_2\":true") ||
      strstr(normalized, "\"relay_2\":\"true\"") ||
      strstr(normalized, "\"relay_2\":1") ||
      strstr(normalized, "\"relay2\":\"on\"") ||
      strstr(normalized, "\"relay\":2,\"state\":\"on\"") ||
      strstr(normalized, "\"relay\":\"2\",\"state\":\"on\"")) {
    setRelay(1, true);
  } else if (strstr(normalized, "\"relay_2\":false") ||
             strstr(normalized, "\"relay_2\":\"false\"") ||
             strstr(normalized, "\"relay_2\":0") ||
             strstr(normalized, "\"relay2\":\"off\"") ||
             strstr(normalized, "\"relay\":2,\"state\":\"off\"") ||
             strstr(normalized, "\"relay\":\"2\",\"state\":\"off\"")) {
    setRelay(1, false);
  }

  if (strstr(normalized, "\"relay_3\":true") ||
      strstr(normalized, "\"relay_3\":\"true\"") ||
      strstr(normalized, "\"relay_3\":1") ||
      strstr(normalized, "\"relay3\":\"on\"") ||
      strstr(normalized, "\"relay\":3,\"state\":\"on\"") ||
      strstr(normalized, "\"relay\":\"3\",\"state\":\"on\"")) {
    setRelay(2, true);
  } else if (strstr(normalized, "\"relay_3\":false") ||
             strstr(normalized, "\"relay_3\":\"false\"") ||
             strstr(normalized, "\"relay_3\":0") ||
             strstr(normalized, "\"relay3\":\"off\"") ||
             strstr(normalized, "\"relay\":3,\"state\":\"off\"") ||
             strstr(normalized, "\"relay\":\"3\",\"state\":\"off\"")) {
    setRelay(2, false);
  }

  if (strstr(normalized, "\"all\":\"on\"") ||
      strstr(normalized, "\"all\":true") ||
      strstr(normalized, "\"all\":\"true\"") ||
      strstr(normalized, "\"all\":1")) {
    for (int i = 0; i < NUM_CHANNELS; i++) setRelay(i, true);
  } else if (strstr(normalized, "\"all\":\"off\"") ||
             strstr(normalized, "\"all\":false") ||
             strstr(normalized, "\"all\":\"false\"") ||
             strstr(normalized, "\"all\":0")) {
    for (int i = 0; i < NUM_CHANNELS; i++) setRelay(i, false);
  }
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
  char msg[128];
  unsigned int n = (length < sizeof(msg) - 1) ? length : sizeof(msg) - 1;

  memcpy(msg, payload, n);
  msg[n] = '\0';

  if (strcmp(topic, mqtt_topic_cmd) == 0) {
    if (msg[0] == '{') {
      parseMqttJson(msg);
      return;
    }

    for (unsigned int i = 0; i < n; i++) {
      msg[i] = toupper(msg[i]);
    }

    processCommand(msg);
  }
}

bool ensureMqttConnected() {
  if (WiFi.status() != WL_CONNECTED) return false;
  if (client.connected()) return true;

  unsigned long now = millis();
  if (now - lastMqttAttemptMs < MQTT_RETRY_INTERVAL_MS) {
    return false;
  }
  lastMqttAttemptMs = now;

  Serial.print("Conectare la MQTT...");
  String clientId = "ESP32S3_3ACS_3RELAY";

  if (client.connect(clientId.c_str())) {
    Serial.println("OK");
    client.subscribe(mqtt_topic_cmd);
    Serial.print("Subscribed: ");
    Serial.println(mqtt_topic_cmd);
    return true;
  }

  Serial.print("Eroare rc=");
  Serial.print(client.state());
  Serial.println(" -> astept retry");
  return false;
}

void publishMQTT(const Measurements& publishSource) {
  if (!ensureMqttConnected()) return;
  client.loop();

  float publishCurrent[NUM_CHANNELS] = {
    publishSource.currentRMS[0],
    publishSource.currentRMS[1],
    publishSource.currentRMS[2]
  };
  float publishPower[NUM_CHANNELS] = {
    publishSource.activePowerW[0] < 0.0f ? 0.0f : publishSource.activePowerW[0],
    publishSource.activePowerW[1] < 0.0f ? 0.0f : publishSource.activePowerW[1],
    publishSource.activePowerW[2] < 0.0f ? 0.0f : publishSource.activePowerW[2]
  };
  float totalCurrent = publishSource.totalCurrentRMS;
  float totalPower = publishPower[0] + publishPower[1] + publishPower[2];

  char payload[384];
  snprintf(payload, sizeof(payload),
           "{\"voltage\":%.1f,\"current\":%.3f,\"current_1\":%.3f,\"current_2\":%.3f,\"current_3\":%.3f,\"power\":%.1f,\"power_1\":%.1f,\"power_2\":%.1f,\"power_3\":%.1f,\"energy\":%.5f,\"relay_1\":%s,\"relay_2\":%s,\"relay_3\":%s}",
           publishSource.voltageRMS,
           totalCurrent,
           publishCurrent[0],
           publishCurrent[1],
           publishCurrent[2],
           totalPower,
           publishPower[0],
           publishPower[1],
           publishPower[2],
           channels[0].energy_kWh + channels[1].energy_kWh + channels[2].energy_kWh,
           channels[0].relayState ? "true" : "false",
           channels[1].relayState ? "true" : "false",
           channels[2].relayState ? "true" : "false");

  client.publish(mqtt_topic_data, payload);
  Serial.print("MQTT publish: ");
  Serial.println(payload);
}

// =====================================================
// SERIAL
// =====================================================
void handleSerial() {
  while (Serial.available()) {
    char c = Serial.read();

    if (c == '\n' || c == '\r') {
      if (serialIndex > 0) {
        serialBuffer[serialIndex] = '\0';
        toUpperStr(serialBuffer);
        processCommand(serialBuffer);
        serialIndex = 0;
      }
    } else {
      if (serialIndex < sizeof(serialBuffer) - 1) {
        serialBuffer[serialIndex++] = c;
      }
    }
  }
}

// =====================================================
// SETUP
// =====================================================
void setup() {
  Serial.begin(115200);
  delay(500);

  analogReadResolution(12);

  analogSetPinAttenuation(ACS1_PIN, ADC_11db);
  analogSetPinAttenuation(ACS2_PIN, ADC_11db);
  analogSetPinAttenuation(ACS3_PIN, ADC_11db);
  analogSetPinAttenuation(VOLTAGE_PIN, ADC_11db);

  for (int i = 0; i < NUM_CHANNELS; i++) {
    pinMode(channels[i].relayPin, OUTPUT);
    digitalWrite(channels[i].relayPin, HIGH);   // releu activ LOW => initial OPRIT
    channels[i].relayState = false;
  }

  setup_wifi();

  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);

  recalibrateSensors();

  lastMeasureMs = millis();
  lastMqttMs = millis();
  lastEnergyMs = millis();

  Serial.println("Sistem pornit.");
  Serial.print("FW: ");
  Serial.println(FW_VERSION);
  Serial.println("Toate releele pornesc OPRITE la startup.");
  Serial.println("Control relee: exclusiv prin MQTT din dashboard sau prin Serial.");
  Serial.println("WiFi provisioning: BLE GATT service PulseNode-Setup.");
  Serial.println("Payload MQTT compatibil site: voltage/current/current_1/current_2/current_3/power/energy/relay_1/relay_2/relay_3");
  Serial.println("Comenzi seriale: ON1 OFF1 ON2 OFF2 ON3 OFF3 ALL_ON ALL_OFF STATUS LIST SCAN CAL RESET_ENERGY HELP");
  Serial.println("BLE: trimite HELP / LIST / SAVE|slot|name|ssid|password|priority|enabled / USE|slot");
}

// =====================================================
// LOOP
// =====================================================
void loop() {
  handleSerial();

  unsigned long now = millis();

  wl_status_t wifiStatus = WiFi.status();
  if (wifiStatus == WL_CONNECTED) {
    lastWifiAttemptMs = now;
  } else if (now - lastWifiAttemptMs >= WIFI_RETRY_INTERVAL_MS) {
    Serial.println("WiFi deconectat. Incerc profilul activ / reconnect...");
    if (activeWifiProfile >= 0) {
      connectToWifiProfile((uint8_t)activeWifiProfile, 8000);
    } else {
      connectBestSavedWifi();
    }
    lastWifiAttemptMs = now;
  }

  if (ensureMqttConnected()) {
    client.loop();
  }

  if (relayCalibrationPending && now >= relayCalibrationDueMs) {
    recalibrateSensors();
    relayCalibrationPending = false;
  }

  if (now - lastMeasureMs >= MEASURE_INTERVAL_MS) {
    meas = measurePower();
    Measurements publishMeas = smoothMeasurements(meas);

    float deltaHours = (now - lastEnergyMs) / 3600000.0f;
    lastEnergyMs = now;

    for (int i = 0; i < NUM_CHANNELS; i++) {
      channels[i].energy_kWh += (meas.activePowerW[i] * deltaHours) / 1000.0;
      if (channels[i].energy_kWh < 0.0) channels[i].energy_kWh = 0.0;
    }

    Serial.printf("U_RMS: %.1f V\n", meas.voltageRMS);
    for (int i = 0; i < NUM_CHANNELS; i++) {
      Serial.printf("CH%d | I: %.3f A | P: %.1f W | S: %.1f VA | PF: %.2f | kWh: %.5f\n",
                    i + 1,
                    meas.currentRMS[i],
                    meas.activePowerW[i],
                    meas.apparentPowerVA[i],
                    meas.powerFactor[i],
                    channels[i].energy_kWh);
    }
    Serial.printf("TOTAL | I: %.3f A | P: %.1f W | S: %.1f VA\n",
                  meas.totalCurrentRMS,
                  meas.totalActivePowerW,
                  meas.totalApparentPowerVA);

    lastMeasureMs = now;
  }

  if (now - lastMqttMs >= MQTT_INTERVAL_MS) {
    publishMQTT(publishMeas);
    lastMqttMs = now;
  }
}
