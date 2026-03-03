#include <WiFi.h>
#include <PubSubClient.h>
#include <math.h>

//-----------------------------------------------------------
// CONFIGURARI
//-----------------------------------------------------------

// WiFi
const char* ssid        = "Test_TP";
const char* password    = "pinguin1";

// MQTT (Public broker)
const char* mqtt_server = "broker.hivemq.com";
const int   mqtt_port   = 1883;

// Topics
const char* mqtt_topic_data = "razvy_esp32_2026/data";
const char* mqtt_topic_cmd  = "razvy_esp32_2026/cmd";

// Clienti WiFi + MQTT
WiFiClient espClient;
PubSubClient client(espClient);

// ACS712 (curent)
#define CURRENT1_PIN 12
#define CURRENT2_PIN 13
#define CURRENT3_PIN 14
const float sensitivity = 0.134;
const float vRef = 3.3;
const int resolution = 4095;

// Divizor 2.2k / 3.3k
const float dividerFactor = (2.2 + 3.3) / 2.2;

// ZMPT101B (tensiune)
#define VOLTAGE_PIN 5
float voltageCalibration = 990;

// Relee
#define RELAY1_PIN 15
#define RELAY2_PIN 16
#define RELAY3_PIN 17
bool relay1State = false;
bool relay2State = false;
bool relay3State = false;

// Energie
double energy_kWh = 0.0;
unsigned long lastMillis;

// Noise ACS712 (în amperi)
float noiseOffset1 = 0.0;
float noiseOffset2 = 0.0;
float noiseOffset3 = 0.0;

// Serial input buffer
String serialBuffer = "";

// Timer MQTT 10 secunde pentru testing
unsigned long lastMQTT = 0;
const unsigned long MQTT_INTERVAL = 10000;

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
// CALLBACK MQTT – CONTROL RELEU
//-----------------------------------------------------------
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String msg = "";
  for (unsigned int i = 0; i < length; i++) msg += (char)payload[i];

  msg.trim();
  msg.toUpperCase();

  if (String(topic) == mqtt_topic_cmd) {
    // JSON format: {"relay":1,"state":"on"}
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

      bool turnOn = stateValue == "ON";

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

    // Compatibilitate veche (control doar releu 1)
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
// MQTT RECONNECT + SUBSCRIBE CMD
//-----------------------------------------------------------
void reconnectMQTT() {
  while (!client.connected()) {
    Serial.print("Conectare la MQTT...");
    String clientId = "ESP32_Priza_1";

    if (client.connect(clientId.c_str())) {
      Serial.println("OK");
      client.subscribe(mqtt_topic_cmd); // IMPORTANT
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
// COMANDA SERIALA – NON BLOCKING
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
        Serial.println("Comenzi seriale: ON/OFF, R1_ON/R1_OFF, R2_ON/R2_OFF, R3_ON/R3_OFF, ALL_ON/ALL_OFF, STATUS, HELP");
      }
      else if (serialBuffer.length() > 0) {
        Serial.print("Comanda necunoscuta: ");
        Serial.println(serialBuffer);
        Serial.println("Scrie HELP pentru lista de comenzi.");
      }

      serialBuffer = "";
    } else {
      serialBuffer += c;
    }
  }
}

//-----------------------------------------------------------
// MASURARE RMS ACS712 – ZERO NOISE + DETECTARE MICĂ
//-----------------------------------------------------------
float readCurrentRMS(int currentPin, float noiseOffset, float &smoothState) {
  const int samples = 1500;

  // offset ADC
  float offset = 0;
  for (int i = 0; i < 800; i++) offset += analogRead(currentPin);
  offset /= 800.0;

  // RMS
  float sum = 0;
  for (int i = 0; i < samples; i++) {
    float raw = analogRead(currentPin);
    float v_adc = (raw - offset) * (vRef / resolution);
    float v_real = v_adc * dividerFactor;
    sum += v_real * v_real;
  }

  float v_rms = sqrt(sum / samples);
  float current = v_rms / sensitivity;

  float threshold = noiseOffset + 0.01;

  if (current <= threshold) current = 0;
  else current -= noiseOffset;

  // filtru exponential moale
  smoothState = smoothState * 0.6 + current * 0.4;

  return smoothState;
}

//-----------------------------------------------------------
// MASURARE RMS TENSIUNE – ZMPT101B
//-----------------------------------------------------------
float readVoltageRMS() {
  const int samples = 1800;

  float offset = 0;
  for (int i = 0; i < 900; i++) offset += analogRead(VOLTAGE_PIN);
  offset /= 900.0;

  float sum = 0;
  for (int i = 0; i < samples; i++) {
    float raw = analogRead(VOLTAGE_PIN);
    float v_adc = (raw - offset) * (vRef / resolution);
    sum += v_adc * v_adc;
  }

  float moduleRMS = sqrt(sum / samples);

  Serial.print("moduleRMS: ");
  Serial.print(moduleRMS, 4);
  Serial.print(" | ");

  return moduleRMS * voltageCalibration;
}

//-----------------------------------------------------------
// TRIMITERE MQTT (DATA)
//-----------------------------------------------------------
void sendMQTT(float voltage_rms, float current1_rms, float current2_rms, float current3_rms, float current_total, float power_W, double energy_kWh) {
  if (!client.connected()) reconnectMQTT();
  client.loop();

  char payload[256];
  snprintf(payload, sizeof(payload),
           "{\"voltage\":%.1f,\"current\":%.3f,\"current_1\":%.3f,\"current_2\":%.3f,\"current_3\":%.3f,\"power\":%.1f,\"energy\":%.5f,\"relay_1\":%s,\"relay_2\":%s,\"relay_3\":%s}",
           voltage_rms, current_total, current1_rms, current2_rms, current3_rms, power_W, energy_kWh,
           relay1State ? "true" : "false",
           relay2State ? "true" : "false",
           relay3State ? "true" : "false");

  Serial.print("MQTT publish: ");
  Serial.println(payload);

  client.publish(mqtt_topic_data, payload);
}

//-----------------------------------------------------------
// SETUP – CALIBRARE NOISE + WIFI + MQTT
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
  digitalWrite(RELAY1_PIN, HIGH);
  digitalWrite(RELAY2_PIN, HIGH);
  digitalWrite(RELAY3_PIN, HIGH);

  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);

  delay(500);

  Serial.println("Calibrare noise ACS712 (3 canale)...");

  const int baseSamples = 1500;
  int acsPins[3] = {CURRENT1_PIN, CURRENT2_PIN, CURRENT3_PIN};
  float* offsets[3] = {&noiseOffset1, &noiseOffset2, &noiseOffset3};

  for (int ch = 0; ch < 3; ch++) {
    int base = 0;
    for (int i = 0; i < baseSamples; i++) base += analogRead(acsPins[ch]);
    base /= baseSamples;

    float noiseSum = 0;
    for (int i = 0; i < baseSamples; i++) {
      int val = analogRead(acsPins[ch]);
      noiseSum += abs(val - base);
    }

    float noiseADC = noiseSum / baseSamples;
    float noiseVolt = noiseADC * (vRef / resolution) * dividerFactor;
    *offsets[ch] = noiseVolt / sensitivity;

    Serial.print("ACS");
    Serial.print(ch + 1);
    Serial.print(" (pin ");
    Serial.print(acsPins[ch]);
    Serial.print(") noise: ");
    Serial.println(*offsets[ch], 5);
  }

  delay(300);

  lastMillis = millis();
  lastMQTT = millis();
  Serial.println("Sistem pornit.");
  Serial.println("Mapare pini ACS: ACS1=12, ACS2=13, ACS3=14");
  Serial.println("Mapare relee: R1=15, R2=16, R3=17");
  Serial.println("Comenzi seriale: ON/OFF, R1_ON/R1_OFF, R2_ON/R2_OFF, R3_ON/R3_OFF, ALL_ON/ALL_OFF, STATUS, HELP");
}

//-----------------------------------------------------------
// LOOP PRINCIPAL
//-----------------------------------------------------------
void loop() {
  handleSerial();

  if (!client.connected()) reconnectMQTT();
  client.loop();

  static float smooth1 = 0;
  static float smooth2 = 0;
  static float smooth3 = 0;

  float current1_rms = readCurrentRMS(CURRENT1_PIN, noiseOffset1, smooth1);
  float current2_rms = readCurrentRMS(CURRENT2_PIN, noiseOffset2, smooth2);
  float current3_rms = readCurrentRMS(CURRENT3_PIN, noiseOffset3, smooth3);
  float current_total = current1_rms + current2_rms + current3_rms;
  float voltage_rms = readVoltageRMS();
  float power_W = voltage_rms * current_total;

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

  // PUBLICĂ MQTT O DATĂ LA 10 SECUNDE (MQTT_INTERVAL)
  if (millis() - lastMQTT >= MQTT_INTERVAL) {
    sendMQTT(voltage_rms, current1_rms, current2_rms, current3_rms, current_total, power_W, energy_kWh);
    lastMQTT = millis();
  }

  delay(500);
}
