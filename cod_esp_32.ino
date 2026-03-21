#include <WiFi.h>
#include <PubSubClient.h>
#include <math.h>
#include <string.h>
#include <stdio.h>
#include <ctype.h>

// =====================================================
// WIFI + MQTT
// =====================================================
const char* FW_VERSION = "esp32-current-refactor-v17";

const char* ssid = "Test_TP";
const char* password = "pinguin1";

const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 1883;

const char* mqtt_topic_data = "razvy_esp32_2026/data";
const char* mqtt_topic_cmd  = "razvy_esp32_2026/cmd";

WiFiClient espClient;
PubSubClient client(espClient);

// =====================================================
// HARDWARE - ESP32-S3
// =====================================================
#define ACS1_PIN    4
#define ACS2_PIN    5
#define ACS3_PIN    6

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
const unsigned long MEASURE_INTERVAL_MS = 1200;
const unsigned long MQTT_INTERVAL_MS    = 5000;
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
// WIFI
// =====================================================
void setup_wifi() {
  delay(100);
  Serial.printf("Conectare la WiFi: %s\n", ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(400);
    Serial.print(".");
  }

  Serial.println();
  Serial.print("Conectat, IP: ");
  Serial.println(WiFi.localIP());
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
    Serial.println("STATUS CAL RESET_ENERGY HELP");
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

void reconnectMQTT() {
  while (!client.connected()) {
    Serial.print("Conectare la MQTT...");
    String clientId = "ESP32S3_3ACS_3RELAY";

    if (client.connect(clientId.c_str())) {
      Serial.println("OK");
      client.subscribe(mqtt_topic_cmd);
      Serial.print("Subscribed: ");
      Serial.println(mqtt_topic_cmd);
    } else {
      Serial.print("Eroare rc=");
      Serial.print(client.state());
      Serial.println(" -> retry in 2 sec");
      delay(2000);
    }
  }
}

void publishMQTT() {
  if (!client.connected()) reconnectMQTT();
  client.loop();

  float publishCurrent[NUM_CHANNELS] = {
    meas.currentRMS[0],
    meas.currentRMS[1],
    meas.currentRMS[2]
  };
  float publishPower[NUM_CHANNELS] = {
    meas.activePowerW[0],
    meas.activePowerW[1],
    meas.activePowerW[2]
  };
  float totalCurrent = meas.totalCurrentRMS;
  float totalPower = meas.totalActivePowerW;

  for (int i = 0; i < NUM_CHANNELS; i++) {
    if (publishPower[i] == 0.0f) {
      publishCurrent[i] = 0.0f;
    }
  }

  totalCurrent = publishCurrent[0] + publishCurrent[1] + publishCurrent[2];
  totalPower = publishPower[0] + publishPower[1] + publishPower[2];

  if (totalPower == 0.0f) {
    totalCurrent = 0.0f;
  }

  char payload[384];
  snprintf(payload, sizeof(payload),
           "{\"voltage\":%.1f,\"current\":%.3f,\"current_1\":%.3f,\"current_2\":%.3f,\"current_3\":%.3f,\"power\":%.1f,\"power_1\":%.1f,\"power_2\":%.1f,\"power_3\":%.1f,\"energy\":%.5f,\"relay_1\":%s,\"relay_2\":%s,\"relay_3\":%s}",
           meas.voltageRMS,
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
  Serial.println("Payload MQTT compatibil site: voltage/current/current_1/current_2/current_3/power/energy/relay_1/relay_2/relay_3");
  Serial.println("Comenzi: ON1 OFF1 ON2 OFF2 ON3 OFF3 ALL_ON ALL_OFF STATUS CAL RESET_ENERGY HELP");
}

// =====================================================
// LOOP
// =====================================================
void loop() {
  handleSerial();

  if (!client.connected()) reconnectMQTT();
  client.loop();

  unsigned long now = millis();

  if (relayCalibrationPending && now >= relayCalibrationDueMs) {
    recalibrateSensors();
    relayCalibrationPending = false;
  }

  if (now - lastMeasureMs >= MEASURE_INTERVAL_MS) {
    meas = measurePower();

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
    publishMQTT();
    lastMqttMs = now;
  }
}
