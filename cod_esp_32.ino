#include <WiFi.h>
#include <PubSubClient.h>
#include <math.h>
#include <string.h>

//-----------------------------------------------------------
// CONFIGURARI
//-----------------------------------------------------------

const char* FW_VERSION = "esp32-current-refactor-v9";

// WiFi
const char* ssid = "Test_TP";
const char* password = "pinguin1";

// MQTT
const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 1883;
const char* mqtt_topic_data = "razvy_esp32_2026/data";
const char* mqtt_topic_cmd = "razvy_esp32_2026/cmd";

WiFiClient espClient;
PubSubClient client(espClient);

const uint8_t CHANNEL_COUNT = 3;
const uint8_t INVALID_CHANNEL_INDEX = 0xFF;

// ACS712
const uint8_t CURRENT_PINS[CHANNEL_COUNT] = {12, 13, 14};
const float sensitivity = 0.134;  // V/A
const float vRef = 3.3;
const int resolution = 4095;
const float dividerFactor = (2.2 + 3.3) / 2.2;  // 3.3k si 2.2k

// ZMPT101B
const uint8_t VOLTAGE_PIN = 5;
float voltageCalibration = 990.0;

// Relee
const uint8_t RELAY_PINS[CHANNEL_COUNT] = {15, 16, 17};
bool relayStates[CHANNEL_COUNT] = {false, false, false};

// Energie
double energy_kWh = 0.0;
unsigned long lastMillis = 0;

// Timer MQTT
unsigned long lastMQTT = 0;
const unsigned long MQTT_INTERVAL = 10000;

// Sampling
const int CURRENT_OFFSET_SAMPLES = 240;
const int CURRENT_RMS_SAMPLES = 480;
const int CURRENT_OFFSET_AVERAGE_READS = 30;
const unsigned long VOLTAGE_OFFSET_WINDOW_US = 40000;   // ~2 perioade la 50Hz
const unsigned long VOLTAGE_RMS_WINDOW_US = 200000;     // ~10 perioade la 50Hz

const float VOLTAGE_FILTER_ALPHA = 0.18;
const float DEFAULT_VOLTAGE_RMS = 224.0;

const float MIN_CURRENT_THRESHOLD = 0.035;
const float CURRENT_HOLD_ZERO_THRESHOLD = 0.025;
const float LOW_CURRENT_STABILIZE_THRESHOLD = 0.18;
const float LOW_CURRENT_FILTER_ALPHA = 0.10;
const float NORMAL_CURRENT_FILTER_ALPHA = 0.20;
const float POWER_FILTER_ALPHA = 0.18;
const float LOW_POWER_STABILIZE_THRESHOLD_W = 30.0;
const float LOW_POWER_FILTER_ALPHA = 0.08;
const float DOMINANCE_RATIO_THRESHOLD = 0.35;
const float DOMINANCE_MIN_ACTIVE_CURRENT = 0.12;
const float CROSSTALK_MATRIX[CHANNEL_COUNT][CHANNEL_COUNT] = {
  {0.0, 0.18, 0.10},
  {0.12, 0.0, 0.10},
  {0.08, 0.16, 0.0}
};
const float MQTT_RESET_CURRENT_LEVEL = 0.04;
const int MQTT_RESET_ZERO_STREAK = 3;
const unsigned long RELAY_SETTLE_MS = 1500;

float currentNoiseFloors[CHANNEL_COUNT] = {0.0, 0.0, 0.0};

enum MQTTMetricIndex {
  MQTT_METRIC_VOLTAGE = 0,
  MQTT_METRIC_POWER,
  MQTT_METRIC_COUNT
};

struct MQTTAverageBuffer {
  float metricSums[MQTT_METRIC_COUNT];
  float channelCurrentSums[CHANNEL_COUNT];
  int metricCounts[MQTT_METRIC_COUNT];
  int channelCurrentCounts[CHANNEL_COUNT];
  int zeroStreaks[CHANNEL_COUNT];
};

MQTTAverageBuffer mqttAverage = {};

void clearMQTTAverages();

//-----------------------------------------------------------
// YIELD PRIETENOS PENTRU WATCHDOG
//-----------------------------------------------------------
inline void watchdogFriendlyYield(int i) {
  if ((i & 0x3F) == 0) {
    delay(0);
  }
}

//-----------------------------------------------------------
// HELPERE GENERALE
//-----------------------------------------------------------
const char* relayStateLabel(bool isOn) {
  return isOn ? "PORNIT" : "OPRIT";
}

uint8_t relayIdToIndex(int relayId) {
  switch (relayId) {
    case 1:
      return 0;
    case 2:
      return 1;
    case 3:
      return 2;
    default:
      return INVALID_CHANNEL_INDEX;
  }
}

float sumChannels(const float values[]) {
  float total = 0.0;
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    total += values[i];
  }
  return total;
}

float safeAverage(float sum, int count, float fallbackValue) {
  if (count <= 0) {
    return fallbackValue;
  }
  return sum / (float)count;
}

//-----------------------------------------------------------
// WIFI
//-----------------------------------------------------------
void setup_wifi() {
  delay(100);
  Serial.print("Conectare la WiFi: ");
  Serial.println(ssid);

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.print("Conectat, IP: ");
  Serial.println(WiFi.localIP());
}

//-----------------------------------------------------------
// CONTROL RELEE
//-----------------------------------------------------------
bool isRelayOn(uint8_t channelIndex) {
  return digitalRead(RELAY_PINS[channelIndex]) == LOW;
}

void syncRelayStatesFromPins() {
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    relayStates[i] = isRelayOn(i);
  }
}

void applyRelayState(uint8_t channelIndex, bool turnOn, const char* sourceLabel) {
  if (channelIndex >= CHANNEL_COUNT) {
    return;
  }

  digitalWrite(RELAY_PINS[channelIndex], turnOn ? LOW : HIGH);
  relayStates[channelIndex] = turnOn;

  if (sourceLabel != nullptr) {
    Serial.print("Releu ");
    Serial.print(channelIndex + 1);
    Serial.print(" ");
    Serial.print(sourceLabel);
    Serial.print(": ");
    Serial.println(relayStateLabel(turnOn));
  }
}

void initializeRelays() {
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    pinMode(RELAY_PINS[i], OUTPUT);
  }

  // Scenariu startup: releul 1 porneste implicit, celelalte raman oprite.
  applyRelayState(0, true, nullptr);
  applyRelayState(1, false, nullptr);
  applyRelayState(2, false, nullptr);
  syncRelayStatesFromPins();
}

bool updateRelayTransitionTracking(float smoothCurrents[], unsigned long &lastRelayChangeMillis) {
  static bool initialized = false;
  static bool lastKnownRelayStates[CHANNEL_COUNT] = {false, false, false};
  bool relayChanged = false;

  if (!initialized) {
    for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
      lastKnownRelayStates[i] = relayStates[i];
    }
    initialized = true;
  }

  // Scenariu: dupa o comutare de releu golim filtrele si mediile MQTT,
  // ca sa nu publicam valori ramase din starea anterioara.
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    if (relayStates[i] != lastKnownRelayStates[i]) {
      smoothCurrents[i] = 0.0;
      lastKnownRelayStates[i] = relayStates[i];
      relayChanged = true;
    }
  }

  if (relayChanged) {
    clearMQTTAverages();
    lastRelayChangeMillis = millis();
  }

  return (millis() - lastRelayChangeMillis) < RELAY_SETTLE_MS;
}

//-----------------------------------------------------------
// MASURARE CURENT
//-----------------------------------------------------------
float measureCurrentRMSRaw(uint8_t currentPin) {
  float offset = 0.0;

  for (int i = 0; i < CURRENT_OFFSET_SAMPLES; i++) {
    offset += analogRead(currentPin);
    watchdogFriendlyYield(i);
  }
  offset /= (float)CURRENT_OFFSET_SAMPLES;

  float sum = 0.0;
  for (int i = 0; i < CURRENT_RMS_SAMPLES; i++) {
    float raw = analogRead(currentPin);
    float v_adc = (raw - offset) * (vRef / resolution);
    float v_sensor = v_adc * dividerFactor;
    sum += v_sensor * v_sensor;
    watchdogFriendlyYield(i);
  }

  float v_rms = sqrt(sum / (float)CURRENT_RMS_SAMPLES);
  return v_rms / sensitivity;
}

float calibrateCurrentNoiseFloor(uint8_t currentPin) {
  float totalCurrent = 0.0;

  for (int i = 0; i < CURRENT_OFFSET_AVERAGE_READS; i++) {
    totalCurrent += measureCurrentRMSRaw(currentPin);
    watchdogFriendlyYield(i);
  }

  return totalCurrent / (float)CURRENT_OFFSET_AVERAGE_READS;
}

float readCurrentRMS(uint8_t channelIndex, float &smoothState) {
  float current = measureCurrentRMSRaw(CURRENT_PINS[channelIndex]) - currentNoiseFloors[channelIndex];

  if (current < 0.0) {
    current = 0.0;
  }

  if (current < MIN_CURRENT_THRESHOLD) {
    current = 0.0;
  }

  // Scenariu: consum mic => filtrare mai calma; consum clar => raspuns mai rapid.
  float filterAlpha = (current < LOW_CURRENT_STABILIZE_THRESHOLD)
                        ? LOW_CURRENT_FILTER_ALPHA
                        : NORMAL_CURRENT_FILTER_ALPHA;
  smoothState = smoothState * (1.0 - filterAlpha) + current * filterAlpha;

  if (smoothState < CURRENT_HOLD_ZERO_THRESHOLD) {
    smoothState = 0.0;
  }

  return smoothState;
}

void readAllChannelCurrents(float channelCurrents[], float smoothCurrents[]) {
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    channelCurrents[i] = readCurrentRMS(i, smoothCurrents[i]);
  }
}

void clampInactiveRelayChannels(float channelCurrents[], float smoothCurrents[] = nullptr) {
  // Scenariu de siguranta: releu OFF inseamna consum imposibil pe acel canal.
  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    if (!relayStates[i]) {
      channelCurrents[i] = 0.0;
      if (smoothCurrents != nullptr) {
        smoothCurrents[i] = 0.0;
      }
    }
  }
}

void applyCrosstalkCompensation(float channelCurrents[]) {
  float rawCurrents[CHANNEL_COUNT];

  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    rawCurrents[i] = channelCurrents[i];
  }

  for (uint8_t channel = 0; channel < CHANNEL_COUNT; channel++) {
    float compensated = rawCurrents[channel];

    for (uint8_t source = 0; source < CHANNEL_COUNT; source++) {
      if (channel == source) {
        continue;
      }
      compensated -= rawCurrents[source] * CROSSTALK_MATRIX[channel][source];
    }

    channelCurrents[channel] = (compensated > 0.0) ? compensated : 0.0;
  }
}

void applyDominanceFilter(float channelCurrents[]) {
  float dominant = channelCurrents[0];

  for (uint8_t i = 1; i < CHANNEL_COUNT; i++) {
    if (channelCurrents[i] > dominant) {
      dominant = channelCurrents[i];
    }
  }

  if (dominant < DOMINANCE_MIN_ACTIVE_CURRENT) {
    return;
  }

  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    if (channelCurrents[i] > 0.0 && channelCurrents[i] < (dominant * DOMINANCE_RATIO_THRESHOLD)) {
      channelCurrents[i] = 0.0;
    }
  }
}

void finalizeMeasuredCurrents(float channelCurrents[], float smoothCurrents[]) {
  // Scenariu 1: daca releul este oprit, orice curent citit este tratat ca zgomot.
  clampInactiveRelayChannels(channelCurrents, smoothCurrents);

  // Scenariu 2: pentru relee active, corectam crosstalk-ul si taiem canalele slabe.
  applyCrosstalkCompensation(channelCurrents);
  applyDominanceFilter(channelCurrents);

  // Scenariu 3: dupa compensari, facem un clamp final pentru canalele cu releu OFF.
  clampInactiveRelayChannels(channelCurrents);
}

//-----------------------------------------------------------
// MEDII MQTT
//-----------------------------------------------------------
void clearMQTTAverages() {
  mqttAverage = {};
}

void addMQTTMetricSample(MQTTMetricIndex metric, float value) {
  mqttAverage.metricSums[metric] += value;
  mqttAverage.metricCounts[metric]++;
}

void resetMQTTChannelAverage(uint8_t channelIndex, float currentValue) {
  mqttAverage.channelCurrentSums[channelIndex] = currentValue;
  mqttAverage.channelCurrentCounts[channelIndex] = 1;
  mqttAverage.zeroStreaks[channelIndex] = 0;
}

void updateMQTTChannelAverage(uint8_t channelIndex, float currentValue) {
  // Scenariu: dupa mai multe probe aproape de zero, resetam media
  // canalului ca sa nu ramana "consum fantoma" in payload-ul MQTT.
  if (currentValue <= MQTT_RESET_CURRENT_LEVEL) {
    mqttAverage.zeroStreaks[channelIndex]++;
    if (mqttAverage.zeroStreaks[channelIndex] >= MQTT_RESET_ZERO_STREAK) {
      resetMQTTChannelAverage(channelIndex, currentValue);
      return;
    }
  } else {
    mqttAverage.zeroStreaks[channelIndex] = 0;
  }

  mqttAverage.channelCurrentSums[channelIndex] += currentValue;
  mqttAverage.channelCurrentCounts[channelIndex]++;
}

void updateMQTTAverages(float voltageRms,
                        const float channelCurrents[],
                        float powerW) {
  addMQTTMetricSample(MQTT_METRIC_VOLTAGE, voltageRms);
  addMQTTMetricSample(MQTT_METRIC_POWER, powerW);

  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    updateMQTTChannelAverage(i, channelCurrents[i]);
  }
}

float getMQTTMetricAverage(MQTTMetricIndex metric, float fallbackValue) {
  return safeAverage(mqttAverage.metricSums[metric], mqttAverage.metricCounts[metric], fallbackValue);
}

float getMQTTChannelAverage(uint8_t channelIndex, float fallbackValue) {
  return safeAverage(mqttAverage.channelCurrentSums[channelIndex],
                     mqttAverage.channelCurrentCounts[channelIndex],
                     fallbackValue);
}

//-----------------------------------------------------------
// MQTT CONTROL
//-----------------------------------------------------------
String payloadToUpperMessage(byte* payload, unsigned int length) {
  String msg = "";
  msg.reserve(length);

  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }

  msg.trim();
  msg.toUpperCase();
  return msg;
}

bool parseRelayJsonCommand(const String &msg, uint8_t &channelIndex, bool &turnOn) {
  int relayPos = msg.indexOf("RELAY");
  int statePos = msg.indexOf("STATE");

  if (!msg.startsWith("{") || relayPos == -1 || statePos == -1) {
    return false;
  }

  int relaySeparator = msg.indexOf(":", relayPos);
  int stateSeparator = msg.indexOf(":", statePos);
  if (relaySeparator == -1 || stateSeparator == -1) {
    return false;
  }

  channelIndex = relayIdToIndex(msg.substring(relaySeparator + 1).toInt());
  if (channelIndex == INVALID_CHANNEL_INDEX) {
    return false;
  }

  String stateValue = msg.substring(stateSeparator + 1);
  stateValue.replace("\"", "");
  stateValue.replace("}", "");
  stateValue.trim();

  if (stateValue == "ON") {
    turnOn = true;
    return true;
  }

  if (stateValue == "OFF") {
    turnOn = false;
    return true;
  }

  return false;
}

void mqttCallback(char* topic, byte* payload, unsigned int length) {
  if (strcmp(topic, mqtt_topic_cmd) != 0) {
    return;
  }

  String msg = payloadToUpperMessage(payload, length);
  uint8_t channelIndex = INVALID_CHANNEL_INDEX;
  bool turnOn = false;

  // Scenariu principal: dashboard-ul trimite JSON cu relay + state.
  if (parseRelayJsonCommand(msg, channelIndex, turnOn)) {
    applyRelayState(channelIndex, turnOn, "MQTT");
    return;
  }

  // Scenariu legacy: pastram compatibilitatea pentru ON/OFF pe releul 1.
  if (msg == "ON") {
    applyRelayState(0, true, "MQTT");
  } else if (msg == "OFF") {
    applyRelayState(0, false, "MQTT");
  }
}

void reconnectMQTT() {
  while (!client.connected()) {
    Serial.print("Conectare la MQTT...");
    String clientId = "ESP32_Priza_1";

    if (client.connect(clientId.c_str())) {
      Serial.println("OK");
      client.subscribe(mqtt_topic_cmd);
      Serial.print("Subscribed: ");
      Serial.println(mqtt_topic_cmd);
    } else {
      Serial.print("Eroare, rc=");
      Serial.print(client.state());
      Serial.println(" reincerc peste 2 sec");
      delay(2000);
    }
  }
}

void ensureMQTTConnection() {
  if (!client.connected()) {
    reconnectMQTT();
  }
  client.loop();
}

//-----------------------------------------------------------
// MASURARE TENSIUNE
//-----------------------------------------------------------
float readVoltageRMS() {
  float offset = 0.0;
  int offsetCount = 0;

  unsigned long offsetStart = micros();
  while ((micros() - offsetStart) < VOLTAGE_OFFSET_WINDOW_US) {
    offset += analogRead(VOLTAGE_PIN);
    offsetCount++;
    watchdogFriendlyYield(offsetCount);
  }

  if (offsetCount <= 0) {
    return 0.0;
  }

  offset /= (float)offsetCount;

  float sum = 0.0;
  int rmsCount = 0;

  unsigned long rmsStart = micros();
  while ((micros() - rmsStart) < VOLTAGE_RMS_WINDOW_US) {
    float raw = analogRead(VOLTAGE_PIN);
    float v_adc = (raw - offset) * (vRef / resolution);
    sum += v_adc * v_adc;
    rmsCount++;
    watchdogFriendlyYield(rmsCount);
  }

  if (rmsCount <= 0) {
    return 0.0;
  }

  float moduleRMS = sqrt(sum / (float)rmsCount);
  float voltageRaw = moduleRMS * voltageCalibration;

  // Scenariu startup: filtrul de tensiune pleaca dintr-o valoare stabila,
  // nu din prima citire care poate fi zgomotoasa.
  static float voltageFiltered = DEFAULT_VOLTAGE_RMS;
  voltageFiltered += VOLTAGE_FILTER_ALPHA * (voltageRaw - voltageFiltered);

  Serial.print("moduleRMS: ");
  Serial.print(moduleRMS, 4);
  Serial.print(" | ");

  return voltageFiltered;
}

//-----------------------------------------------------------
// AGREGARE SI TELEMETRIE
//-----------------------------------------------------------
float filterPowerReadings(float voltageRms, float &currentTotal) {
  static float filteredCurrentTotal = 0.0;
  static float filteredPowerW = 0.0;

  float rawPowerW = voltageRms * currentTotal;
  float powerFilterAlpha = (rawPowerW < LOW_POWER_STABILIZE_THRESHOLD_W)
                             ? LOW_POWER_FILTER_ALPHA
                             : POWER_FILTER_ALPHA;

  filteredCurrentTotal += powerFilterAlpha * (currentTotal - filteredCurrentTotal);
  filteredPowerW += powerFilterAlpha * ((voltageRms * filteredCurrentTotal) - filteredPowerW);

  currentTotal = filteredCurrentTotal;
  if (currentTotal < MIN_CURRENT_THRESHOLD) {
    currentTotal = 0.0;
    filteredCurrentTotal = 0.0;
    filteredPowerW = 0.0;
    return 0.0;
  }

  return filteredPowerW;
}

void updateEnergy(float powerW) {
  unsigned long now = millis();
  float deltaHours = (now - lastMillis) / 3600000.0;
  lastMillis = now;
  energy_kWh += (powerW * deltaHours) / 1000.0;
}

void printMeasurements(float voltageRms,
                       const float channelCurrents[],
                       float currentTotal,
                       float powerW) {
  Serial.print("U_RMS: ");
  Serial.print(voltageRms, 1);
  Serial.print(" V | I1: ");
  Serial.print(channelCurrents[0], 3);
  Serial.print(" A | I2: ");
  Serial.print(channelCurrents[1], 3);
  Serial.print(" A | I3: ");
  Serial.print(channelCurrents[2], 3);
  Serial.print(" A | I_TOTAL: ");
  Serial.print(currentTotal, 3);
  Serial.print(" A | P: ");
  Serial.print(powerW, 1);
  Serial.print(" W | kWh: ");
  Serial.println(energy_kWh, 5);
}

void sendMQTT(float voltageRms,
              const float channelCurrents[],
              float currentTotal,
              float powerW,
              double energyKWh) {
  ensureMQTTConnection();

  char payload[256];
  snprintf(payload, sizeof(payload),
           "{\"voltage\":%.1f,\"current\":%.3f,\"current_1\":%.3f,\"current_2\":%.3f,\"current_3\":%.3f,\"power\":%.1f,\"energy\":%.5f,\"relay_1\":%s,\"relay_2\":%s,\"relay_3\":%s}",
           voltageRms,
           currentTotal,
           channelCurrents[0],
           channelCurrents[1],
           channelCurrents[2],
           powerW,
           energyKWh,
           relayStates[0] ? "true" : "false",
           relayStates[1] ? "true" : "false",
           relayStates[2] ? "true" : "false");

  Serial.print("MQTT publish: ");
  Serial.println(payload);

  client.publish(mqtt_topic_data, payload);
}

void publishAveragedMQTT(float liveVoltageRms,
                         const float liveChannelCurrents[],
                         float livePowerW) {
  float mqttChannelCurrents[CHANNEL_COUNT];

  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    mqttChannelCurrents[i] = getMQTTChannelAverage(i, liveChannelCurrents[i]);
  }

  float mqttVoltage = getMQTTMetricAverage(MQTT_METRIC_VOLTAGE, liveVoltageRms);
  float mqttPower = getMQTTMetricAverage(MQTT_METRIC_POWER, livePowerW);

  applyCrosstalkCompensation(mqttChannelCurrents);
  applyDominanceFilter(mqttChannelCurrents);
  clampInactiveRelayChannels(mqttChannelCurrents);

  float mqttCurrentTotal = sumChannels(mqttChannelCurrents);
  if (mqttCurrentTotal < MIN_CURRENT_THRESHOLD) {
    mqttCurrentTotal = 0.0;
    mqttPower = 0.0;
  }

  sendMQTT(mqttVoltage, mqttChannelCurrents, mqttCurrentTotal, mqttPower, energy_kWh);
  clearMQTTAverages();
}

//-----------------------------------------------------------
// SETUP
//-----------------------------------------------------------
void setup() {
  Serial.begin(115200);

  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    analogSetPinAttenuation(CURRENT_PINS[i], ADC_11db);
  }
  analogSetPinAttenuation(VOLTAGE_PIN, ADC_11db);

  initializeRelays();
  setup_wifi();

  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);

  delay(500);

  Serial.println("Sistem pornit.");
  Serial.print("FW: ");
  Serial.println(FW_VERSION);
  Serial.println("Relay 1 este PORNIT automat la startup.");
  Serial.println("Calibrare noise floor ACS la startup: ACTIVA");

  for (uint8_t i = 0; i < CHANNEL_COUNT; i++) {
    currentNoiseFloors[i] = calibrateCurrentNoiseFloor(CURRENT_PINS[i]);
    Serial.print("Noise floor ACS");
    Serial.print(i + 1);
    Serial.print(": ");
    Serial.print(currentNoiseFloors[i], 4);
    Serial.println(" A");
  }

  Serial.println("Se foloseste offset ADC recalculat la fiecare citire + compensare noise floor + filtru de dominanta intre socket-uri.");
  Serial.println("Tensiunea filtrata porneste din valoarea implicita 224V.");
  Serial.println("Mapare pini ACS: ACS1=12, ACS2=13, ACS3=14");
  Serial.println("Mapare relee: R1=15, R2=16, R3=17");
  Serial.println("Control relee: exclusiv prin MQTT din dashboard.");

  lastMillis = millis();
  lastMQTT = millis();
}

//-----------------------------------------------------------
// LOOP
//-----------------------------------------------------------
void loop() {
  static float smoothCurrents[CHANNEL_COUNT] = {0.0, 0.0, 0.0};
  static unsigned long lastRelayChangeMillis = 0;

  ensureMQTTConnection();
  syncRelayStatesFromPins();

  bool relaySettling = updateRelayTransitionTracking(smoothCurrents, lastRelayChangeMillis);

  float channelCurrents[CHANNEL_COUNT] = {0.0, 0.0, 0.0};
  readAllChannelCurrents(channelCurrents, smoothCurrents);
  finalizeMeasuredCurrents(channelCurrents, smoothCurrents);

  float currentTotal = sumChannels(channelCurrents);
  float voltageRms = readVoltageRMS();
  float powerW = filterPowerReadings(voltageRms, currentTotal);

  updateEnergy(powerW);
  printMeasurements(voltageRms, channelCurrents, currentTotal, powerW);

  if (!relaySettling) {
    updateMQTTAverages(voltageRms, channelCurrents, powerW);
  }

  if (!relaySettling && millis() - lastMQTT >= MQTT_INTERVAL) {
    publishAveragedMQTT(voltageRms, channelCurrents, powerW);
    lastMQTT = millis();
  }

  delay(500);
}
