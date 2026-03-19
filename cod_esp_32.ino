#include <WiFi.h>
#include <PubSubClient.h>
#include <math.h>

//-----------------------------------------------------------
// CONFIGURARI
//-----------------------------------------------------------

const char* FW_VERSION = "esp32-current-fix-v7";

// WiFi
const char* ssid        = "Test_TP";
const char* password    = "pinguin1";

// MQTT
const char* mqtt_server = "broker.hivemq.com";
const int   mqtt_port   = 1883;

// Topics
const char* mqtt_topic_data = "razvy_esp32_2026/data";
const char* mqtt_topic_cmd  = "razvy_esp32_2026/cmd";

// Clienti WiFi + MQTT
WiFiClient espClient;
PubSubClient client(espClient);

// ACS712
#define CURRENT1_PIN 12
#define CURRENT2_PIN 13
#define CURRENT3_PIN 14

const float sensitivity = 0.134;   // V/A
const float vRef = 3.3;
const int resolution = 4095;

// Divizor rezistiv ACS -> ESP32: 3.3k si 2.2k
const float dividerFactor = (2.2 + 3.3) / 2.2;

// ZMPT101B
#define VOLTAGE_PIN 5
float voltageCalibration = 990.0;

// Relee
#define RELAY1_PIN 15
#define RELAY2_PIN 16
#define RELAY3_PIN 17

bool relay1State = false;
bool relay2State = false;
bool relay3State = false;

// Energie
double energy_kWh = 0.0;
unsigned long lastMillis = 0;

// Serial input buffer
String serialBuffer = "";

// Timer MQTT
unsigned long lastMQTT = 0;
const unsigned long MQTT_INTERVAL = 10000;

// Sampling
const int CURRENT_OFFSET_SAMPLES = 240;
const int CURRENT_RMS_SAMPLES    = 480;
const int VOLTAGE_OFFSET_SAMPLES = 300;
const int VOLTAGE_RMS_SAMPLES    = 600;
const int CURRENT_OFFSET_AVERAGE_READS = 30;

const unsigned long VOLTAGE_OFFSET_WINDOW_US = 40000;   // ~2 perioade la 50Hz
const unsigned long VOLTAGE_RMS_WINDOW_US    = 200000;  // ~10 perioade la 50Hz

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
const float CROSSTALK_12 = 0.18;
const float CROSSTALK_13 = 0.10;
const float CROSSTALK_21 = 0.12;
const float CROSSTALK_23 = 0.10;
const float CROSSTALK_31 = 0.08;
const float CROSSTALK_32 = 0.16;
const float MQTT_RESET_CURRENT_LEVEL = 0.04;
const int MQTT_RESET_ZERO_STREAK = 3;
const unsigned long RELAY_SETTLE_MS = 1500;

float currentNoiseFloor1 = 0.0;
float currentNoiseFloor2 = 0.0;
float currentNoiseFloor3 = 0.0;

struct MQTTAverageBuffer {
  float voltageSum;
  float current1Sum;
  float current2Sum;
  float current3Sum;
  float totalCurrentSum;
  float powerSum;
  int voltageCount;
  int current1Count;
  int current2Count;
  int current3Count;
  int totalCurrentCount;
  int powerCount;
  int zeroStreak1;
  int zeroStreak2;
  int zeroStreak3;
};

MQTTAverageBuffer mqttAverage = {0};

//-----------------------------------------------------------
// YIELD PRIETENOS PENTRU WATCHDOG
//-----------------------------------------------------------
inline void watchdogFriendlyYield(int i) {
  if ((i & 0x3F) == 0) {
    delay(0);
  }
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
// MASURARE CURENT RMS BRUT
//-----------------------------------------------------------
float measureCurrentRMSRaw(int currentPin) {
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

//-----------------------------------------------------------
// CALIBRARE NOISE FLOOR ACS LA STARTUP
//-----------------------------------------------------------
float calibrateCurrentNoiseFloor(int currentPin) {
  float totalCurrent = 0.0;

  for (int i = 0; i < CURRENT_OFFSET_AVERAGE_READS; i++) {
    float rawCurrent = measureCurrentRMSRaw(currentPin);
    totalCurrent += rawCurrent;
    watchdogFriendlyYield(i);
  }

  return totalCurrent / (float)CURRENT_OFFSET_AVERAGE_READS;
}

void resetMQTTChannelAverage(float &sum, int &count, int &zeroStreak, float currentValue) {
  sum = currentValue;
  count = 1;
  zeroStreak = 0;
}

void updateMQTTAverages(float voltage_rms,
                        float current1_rms,
                        float current2_rms,
                        float current3_rms,
                        float current_total,
                        float power_W) {
  mqttAverage.voltageSum += voltage_rms;
  mqttAverage.voltageCount++;

  mqttAverage.powerSum += power_W;
  mqttAverage.powerCount++;

  mqttAverage.totalCurrentSum += current_total;
  mqttAverage.totalCurrentCount++;

  if (current1_rms <= MQTT_RESET_CURRENT_LEVEL) {
    mqttAverage.zeroStreak1++;
    if (mqttAverage.zeroStreak1 >= MQTT_RESET_ZERO_STREAK) {
      resetMQTTChannelAverage(mqttAverage.current1Sum, mqttAverage.current1Count, mqttAverage.zeroStreak1, current1_rms);
    } else {
      mqttAverage.current1Sum += current1_rms;
      mqttAverage.current1Count++;
    }
  } else {
    mqttAverage.zeroStreak1 = 0;
    mqttAverage.current1Sum += current1_rms;
    mqttAverage.current1Count++;
  }

  if (current2_rms <= MQTT_RESET_CURRENT_LEVEL) {
    mqttAverage.zeroStreak2++;
    if (mqttAverage.zeroStreak2 >= MQTT_RESET_ZERO_STREAK) {
      resetMQTTChannelAverage(mqttAverage.current2Sum, mqttAverage.current2Count, mqttAverage.zeroStreak2, current2_rms);
    } else {
      mqttAverage.current2Sum += current2_rms;
      mqttAverage.current2Count++;
    }
  } else {
    mqttAverage.zeroStreak2 = 0;
    mqttAverage.current2Sum += current2_rms;
    mqttAverage.current2Count++;
  }

  if (current3_rms <= MQTT_RESET_CURRENT_LEVEL) {
    mqttAverage.zeroStreak3++;
    if (mqttAverage.zeroStreak3 >= MQTT_RESET_ZERO_STREAK) {
      resetMQTTChannelAverage(mqttAverage.current3Sum, mqttAverage.current3Count, mqttAverage.zeroStreak3, current3_rms);
    } else {
      mqttAverage.current3Sum += current3_rms;
      mqttAverage.current3Count++;
    }
  } else {
    mqttAverage.zeroStreak3 = 0;
    mqttAverage.current3Sum += current3_rms;
    mqttAverage.current3Count++;
  }
}

float safeAverage(float sum, int count, float fallbackValue) {
  if (count <= 0) {
    return fallbackValue;
  }
  return sum / (float)count;
}

void clearMQTTAverages() {
  mqttAverage = {0};
}

bool isRelayOn(uint8_t relayPin) {
  return digitalRead(relayPin) == LOW;
}

void syncRelayStatesFromPins() {
  relay1State = isRelayOn(RELAY1_PIN);
  relay2State = isRelayOn(RELAY2_PIN);
  relay3State = isRelayOn(RELAY3_PIN);
}

//-----------------------------------------------------------
// CALLBACK MQTT – CONTROL RELEE
//-----------------------------------------------------------
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String msg = "";
  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }

  msg.trim();
  msg.toUpperCase();

  if (String(topic) == mqtt_topic_cmd) {
    // Format JSON: {"relay":1,"state":"on"}
    int relayPos = msg.indexOf("RELAY");
    int statePos = msg.indexOf("STATE");

    if (msg.startsWith("{") && relayPos != -1 && statePos != -1) {
      int relayIdx = msg.indexOf(":", relayPos);
      int stateIdx = msg.indexOf(":", statePos);

      int relayId = msg.substring(relayIdx + 1).toInt();
      String stateValue = msg.substring(stateIdx + 1);
      stateValue.replace("\"", "");
      stateValue.replace("}", "");
      stateValue.trim();

      bool turnOn = (stateValue == "ON");

      if (relayId == 1) {
        digitalWrite(RELAY1_PIN, turnOn ? LOW : HIGH);
        relay1State = turnOn;
      } else if (relayId == 2) {
        digitalWrite(RELAY2_PIN, turnOn ? LOW : HIGH);
        relay2State = turnOn;
      } else if (relayId == 3) {
        digitalWrite(RELAY3_PIN, turnOn ? LOW : HIGH);
        relay3State = turnOn;
      }

      Serial.print("Releu MQTT: ");
      Serial.print(relayId);
      Serial.print(" -> ");
      Serial.println(turnOn ? "PORNIT" : "OPRIT");
      return;
    }

    // Compatibilitate simpla
    if (msg == "ON") {
      digitalWrite(RELAY1_PIN, LOW);
      relay1State = true;
      Serial.println("Releu MQTT 1: PORNIT");
    } else if (msg == "OFF") {
      digitalWrite(RELAY1_PIN, HIGH);
      relay1State = false;
      Serial.println("Releu MQTT 1: OPRIT");
    }
  }
}

//-----------------------------------------------------------
// MQTT RECONNECT
//-----------------------------------------------------------
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

//-----------------------------------------------------------
// COMENZI DIN SERIAL
//-----------------------------------------------------------
void handleSerial() {
  while (Serial.available()) {
    char c = Serial.read();

    if (c == '\n' || c == '\r') {
      serialBuffer.trim();
      serialBuffer.toUpperCase();

      if (serialBuffer == "ON" || serialBuffer == "R1_ON") {
        digitalWrite(RELAY1_PIN, LOW);
        relay1State = true;
        Serial.println("Releu 1: PORNIT");
      }
      else if (serialBuffer == "OFF" || serialBuffer == "R1_OFF") {
        digitalWrite(RELAY1_PIN, HIGH);
        relay1State = false;
        Serial.println("Releu 1: OPRIT");
      }
      else if (serialBuffer == "R2_ON") {
        digitalWrite(RELAY2_PIN, LOW);
        relay2State = true;
        Serial.println("Releu 2: PORNIT");
      }
      else if (serialBuffer == "R2_OFF") {
        digitalWrite(RELAY2_PIN, HIGH);
        relay2State = false;
        Serial.println("Releu 2: OPRIT");
      }
      else if (serialBuffer == "R3_ON") {
        digitalWrite(RELAY3_PIN, LOW);
        relay3State = true;
        Serial.println("Releu 3: PORNIT");
      }
      else if (serialBuffer == "R3_OFF") {
        digitalWrite(RELAY3_PIN, HIGH);
        relay3State = false;
        Serial.println("Releu 3: OPRIT");
      }
      else if (serialBuffer == "ALL_ON") {
        digitalWrite(RELAY1_PIN, LOW);
        digitalWrite(RELAY2_PIN, LOW);
        digitalWrite(RELAY3_PIN, LOW);
        relay1State = true;
        relay2State = true;
        relay3State = true;
        Serial.println("Toate releele: PORNITE");
      }
      else if (serialBuffer == "ALL_OFF") {
        digitalWrite(RELAY1_PIN, HIGH);
        digitalWrite(RELAY2_PIN, HIGH);
        digitalWrite(RELAY3_PIN, HIGH);
        relay1State = false;
        relay2State = false;
        relay3State = false;
        Serial.println("Toate releele: OPRITE");
      }
      else if (serialBuffer == "STATUS") {
        Serial.print("Releu 1: ");
        Serial.println(relay1State ? "PORNIT" : "OPRIT");
        Serial.print("Releu 2: ");
        Serial.println(relay2State ? "PORNIT" : "OPRIT");
        Serial.print("Releu 3: ");
        Serial.println(relay3State ? "PORNIT" : "OPRIT");
      }
      else if (serialBuffer == "HELP") {
        Serial.println("Comenzi: ON/OFF, R1_ON/R1_OFF, R2_ON/R2_OFF, R3_ON/R3_OFF, ALL_ON/ALL_OFF, STATUS, HELP");
      }
      else if (serialBuffer.length() > 0) {
        Serial.print("Comanda necunoscuta: ");
        Serial.println(serialBuffer);
      }

      serialBuffer = "";
    } else {
      serialBuffer += c;
    }
  }
}

//-----------------------------------------------------------
// MASURARE CURENT RMS CU COMPENSARE DE NOISE FLOOR
//-----------------------------------------------------------
float readCurrentRMS(int currentPin, float noiseFloor, float &smoothState) {
  float current = measureCurrentRMSRaw(currentPin) - noiseFloor;

  if (current < 0.0) {
    current = 0.0;
  }

  if (current < MIN_CURRENT_THRESHOLD) {
    current = 0.0;
  }

  // Pentru consumatori mici vrem mai multa stabilitate decat reactie instantanee.
  float filterAlpha = (current < LOW_CURRENT_STABILIZE_THRESHOLD)
                        ? LOW_CURRENT_FILTER_ALPHA
                        : NORMAL_CURRENT_FILTER_ALPHA;
  smoothState = smoothState * (1.0 - filterAlpha) + current * filterAlpha;

  if (smoothState < CURRENT_HOLD_ZERO_THRESHOLD) {
    smoothState = 0.0;
  }

  return smoothState;
}

void applyDominanceFilter(float &current1, float &current2, float &current3) {
  float dominant = current1;
  if (current2 > dominant) {
    dominant = current2;
  }
  if (current3 > dominant) {
    dominant = current3;
  }

  if (dominant < DOMINANCE_MIN_ACTIVE_CURRENT) {
    return;
  }

  if (current1 > 0.0 && current1 < (dominant * DOMINANCE_RATIO_THRESHOLD)) {
    current1 = 0.0;
  }
  if (current2 > 0.0 && current2 < (dominant * DOMINANCE_RATIO_THRESHOLD)) {
    current2 = 0.0;
  }
  if (current3 > 0.0 && current3 < (dominant * DOMINANCE_RATIO_THRESHOLD)) {
    current3 = 0.0;
  }
}

void applyCrosstalkCompensation(float &current1, float &current2, float &current3) {
  float raw1 = current1;
  float raw2 = current2;
  float raw3 = current3;

  current1 = raw1 - (raw2 * CROSSTALK_12) - (raw3 * CROSSTALK_13);
  current2 = raw2 - (raw1 * CROSSTALK_21) - (raw3 * CROSSTALK_23);
  current3 = raw3 - (raw1 * CROSSTALK_31) - (raw2 * CROSSTALK_32);

  if (current1 < 0.0) {
    current1 = 0.0;
  }
  if (current2 < 0.0) {
    current2 = 0.0;
  }
  if (current3 < 0.0) {
    current3 = 0.0;
  }
}

//-----------------------------------------------------------
// MASURARE TENSIUNE RMS – ZMPT101B
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

  // Pornim filtrarea dintr-o valoare implicita stabila, nu din prima citire.
  static float voltageFiltered = DEFAULT_VOLTAGE_RMS;
  voltageFiltered += VOLTAGE_FILTER_ALPHA * (voltageRaw - voltageFiltered);

  Serial.print("moduleRMS: ");
  Serial.print(moduleRMS, 4);
  Serial.print(" | ");

  return voltageFiltered;
}

//-----------------------------------------------------------
// TRIMITERE MQTT
//-----------------------------------------------------------
void sendMQTT(float voltage_rms,
              float current1_rms,
              float current2_rms,
              float current3_rms,
              float current_total,
              float power_W,
              double energy_kWh) {
  if (!client.connected()) {
    reconnectMQTT();
  }
  client.loop();

  char payload[256];
  snprintf(payload, sizeof(payload),
           "{\"voltage\":%.1f,\"current\":%.3f,\"current_1\":%.3f,\"current_2\":%.3f,\"current_3\":%.3f,\"power\":%.1f,\"energy\":%.5f,\"relay_1\":%s,\"relay_2\":%s,\"relay_3\":%s}",
           voltage_rms,
           current_total,
           current1_rms,
           current2_rms,
           current3_rms,
           power_W,
           energy_kWh,
           relay1State ? "true" : "false",
           relay2State ? "true" : "false",
           relay3State ? "true" : "false");

  Serial.print("MQTT publish: ");
  Serial.println(payload);

  client.publish(mqtt_topic_data, payload);
}

//-----------------------------------------------------------
// SETUP
//-----------------------------------------------------------
void setup() {
  Serial.begin(115200);

  analogSetPinAttenuation(CURRENT1_PIN, ADC_11db);
  analogSetPinAttenuation(CURRENT2_PIN, ADC_11db);
  analogSetPinAttenuation(CURRENT3_PIN, ADC_11db);
  analogSetPinAttenuation(VOLTAGE_PIN, ADC_11db);

  pinMode(RELAY1_PIN, OUTPUT);
  pinMode(RELAY2_PIN, OUTPUT);
  pinMode(RELAY3_PIN, OUTPUT);

  // RELEU 1 PORNIT IMEDIAT LA START
  digitalWrite(RELAY1_PIN, LOW);   // active LOW => ON
  relay1State = true;

  // celelalte doua oprite
  digitalWrite(RELAY2_PIN, HIGH);
  digitalWrite(RELAY3_PIN, HIGH);
  relay2State = false;
  relay3State = false;

  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);

  delay(500);

  Serial.println("Sistem pornit.");
  Serial.print("FW: ");
  Serial.println(FW_VERSION);
  Serial.println("Relay 1 este PORNIT automat la startup.");
  Serial.println("Calibrare noise floor ACS la startup: ACTIVA");

  currentNoiseFloor1 = calibrateCurrentNoiseFloor(CURRENT1_PIN);
  currentNoiseFloor2 = calibrateCurrentNoiseFloor(CURRENT2_PIN);
  currentNoiseFloor3 = calibrateCurrentNoiseFloor(CURRENT3_PIN);

  Serial.print("Noise floor ACS1: ");
  Serial.print(currentNoiseFloor1, 4);
  Serial.println(" A");
  Serial.print("Noise floor ACS2: ");
  Serial.print(currentNoiseFloor2, 4);
  Serial.println(" A");
  Serial.print("Noise floor ACS3: ");
  Serial.print(currentNoiseFloor3, 4);
  Serial.println(" A");

  Serial.println("Se foloseste offset ADC recalculat la fiecare citire + compensare noise floor + filtru de dominanta intre socket-uri.");
  Serial.println("Tensiunea filtrata porneste din valoarea implicita 224V.");
  Serial.println("Mapare pini ACS: ACS1=12, ACS2=13, ACS3=14");
  Serial.println("Mapare relee: R1=15, R2=16, R3=17");
  Serial.println("Comenzi seriale: ON/OFF, R1_ON/R1_OFF, R2_ON/R2_OFF, R3_ON/R3_OFF, ALL_ON/ALL_OFF, STATUS, HELP");

  lastMillis = millis();
  lastMQTT = millis();
}

//-----------------------------------------------------------
// LOOP
//-----------------------------------------------------------
void loop() {
  handleSerial();
  syncRelayStatesFromPins();

  if (!client.connected()) {
    reconnectMQTT();
  }
  client.loop();
  syncRelayStatesFromPins();

  static float smooth1 = 0.0;
  static float smooth2 = 0.0;
  static float smooth3 = 0.0;
  static bool lastRelay1State = relay1State;
  static bool lastRelay2State = relay2State;
  static bool lastRelay3State = relay3State;
  static unsigned long lastRelayChangeMillis = 0;
  bool relay1On = relay1State;
  bool relay2On = relay2State;
  bool relay3On = relay3State;

  if (relay1On != lastRelay1State) {
    smooth1 = 0.0;
    clearMQTTAverages();
    lastRelayChangeMillis = millis();
    lastRelay1State = relay1On;
  }
  if (relay2On != lastRelay2State) {
    smooth2 = 0.0;
    clearMQTTAverages();
    lastRelayChangeMillis = millis();
    lastRelay2State = relay2On;
  }
  if (relay3On != lastRelay3State) {
    smooth3 = 0.0;
    clearMQTTAverages();
    lastRelayChangeMillis = millis();
    lastRelay3State = relay3On;
  }

  bool relaySettling = (millis() - lastRelayChangeMillis) < RELAY_SETTLE_MS;

  float current1_rms = readCurrentRMS(CURRENT1_PIN, currentNoiseFloor1, smooth1);
  float current2_rms = readCurrentRMS(CURRENT2_PIN, currentNoiseFloor2, smooth2);
  float current3_rms = readCurrentRMS(CURRENT3_PIN, currentNoiseFloor3, smooth3);

  // Daca releul este oprit, canalul respectiv nu poate avea consum real.
  if (!relay1On) {
    current1_rms = 0.0;
    smooth1 = 0.0;
  }
  if (!relay2On) {
    current2_rms = 0.0;
    smooth2 = 0.0;
  }
  if (!relay3On) {
    current3_rms = 0.0;
    smooth3 = 0.0;
  }

  applyCrosstalkCompensation(current1_rms, current2_rms, current3_rms);
  applyDominanceFilter(current1_rms, current2_rms, current3_rms);

  // Safety clamp final: niciun canal cu releu OFF nu trebuie sa mai reapara.
  if (!relay1On) {
    current1_rms = 0.0;
  }
  if (!relay2On) {
    current2_rms = 0.0;
  }
  if (!relay3On) {
    current3_rms = 0.0;
  }

  float current_total = current1_rms + current2_rms + current3_rms;
  float voltage_rms = readVoltageRMS();

  static float filteredCurrentTotal = 0.0;
  static float filteredPowerW = 0.0;
  float rawPowerW = voltage_rms * current_total;
  float powerFilterAlpha = (rawPowerW < LOW_POWER_STABILIZE_THRESHOLD_W)
                             ? LOW_POWER_FILTER_ALPHA
                             : POWER_FILTER_ALPHA;
  filteredCurrentTotal += powerFilterAlpha * (current_total - filteredCurrentTotal);
  filteredPowerW += powerFilterAlpha * ((voltage_rms * filteredCurrentTotal) - filteredPowerW);

  current_total = filteredCurrentTotal;
  float power_W = filteredPowerW;
  if (current_total < MIN_CURRENT_THRESHOLD) {
    current_total = 0.0;
    power_W = 0.0;
    filteredCurrentTotal = 0.0;
    filteredPowerW = 0.0;
  }

  unsigned long now = millis();
  float deltaHours = (now - lastMillis) / 3600000.0;
  lastMillis = now;

  energy_kWh += (power_W * deltaHours) / 1000.0;

  Serial.print("U_RMS: ");
  Serial.print(voltage_rms, 1);
  Serial.print(" V | I1: ");
  Serial.print(current1_rms, 3);
  Serial.print(" A | I2: ");
  Serial.print(current2_rms, 3);
  Serial.print(" A | I3: ");
  Serial.print(current3_rms, 3);
  Serial.print(" A | I_TOTAL: ");
  Serial.print(current_total, 3);
  Serial.print(" A | P: ");
  Serial.print(power_W, 1);
  Serial.print(" W | kWh: ");
  Serial.println(energy_kWh, 5);

  if (!relaySettling) {
    updateMQTTAverages(voltage_rms,
                       current1_rms,
                       current2_rms,
                       current3_rms,
                       current_total,
                       power_W);
  }

  if (!relaySettling && millis() - lastMQTT >= MQTT_INTERVAL) {
    float mqttVoltage = safeAverage(mqttAverage.voltageSum, mqttAverage.voltageCount, voltage_rms);
    float mqttCurrent1 = safeAverage(mqttAverage.current1Sum, mqttAverage.current1Count, current1_rms);
    float mqttCurrent2 = safeAverage(mqttAverage.current2Sum, mqttAverage.current2Count, current2_rms);
    float mqttCurrent3 = safeAverage(mqttAverage.current3Sum, mqttAverage.current3Count, current3_rms);
    float mqttPower = safeAverage(mqttAverage.powerSum, mqttAverage.powerCount, power_W);

    applyCrosstalkCompensation(mqttCurrent1, mqttCurrent2, mqttCurrent3);
    applyDominanceFilter(mqttCurrent1, mqttCurrent2, mqttCurrent3);

    if (!relay1On) {
      mqttCurrent1 = 0.0;
    }
    if (!relay2On) {
      mqttCurrent2 = 0.0;
    }
    if (!relay3On) {
      mqttCurrent3 = 0.0;
    }

    float mqttCurrentTotal = mqttCurrent1 + mqttCurrent2 + mqttCurrent3;
    if (mqttCurrentTotal < MIN_CURRENT_THRESHOLD) {
      mqttCurrentTotal = 0.0;
      mqttPower = 0.0;
    }

    sendMQTT(mqttVoltage,
             mqttCurrent1,
             mqttCurrent2,
             mqttCurrent3,
             mqttCurrentTotal,
             mqttPower,
             energy_kWh);
    lastMQTT = millis();
    clearMQTTAverages();
  }

  delay(500);
}
