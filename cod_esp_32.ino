#include <WiFi.h>
#include <PubSubClient.h>
#include <math.h>

//-----------------------------------------------------------
// CONFIGURARI
//-----------------------------------------------------------

// WiFi
const char* ssid        = "NUME_WIFI";
const char* password    = "PAROLA_WIFI";

// MQTT (Raspberry Pi broker)
const char* mqtt_server = "192.168.1.246";
const int   mqtt_port   = 1883;

// Topics
const char* mqtt_topic_data = "esp32/data";
const char* mqtt_topic_cmd  = "esp32/cmd";

// Clienti WiFi + MQTT
WiFiClient espClient;
PubSubClient client(espClient);

// ACS712 (curent)
#define CURRENT_PIN 4
const float sensitivity = 0.134;
const float vRef = 3.3;
const int resolution = 4095;

// Divizor 2.2k / 3.3k
const float dividerFactor = (2.2 + 3.3) / 2.2;

// ZMPT101B (tensiune)
#define VOLTAGE_PIN 5
float voltageCalibration = 990;

// Releu
#define RELAY_PIN 6
bool relayState = false;

// Energie
double energy_kWh = 0.0;
unsigned long lastMillis;

// Noise ACS712 (în amperi)
float noiseOffset = 0.0;

// Serial input buffer
String serialBuffer = "";

// Timer MQTT 1 minut
unsigned long lastMQTT = 0;
const unsigned long MQTT_INTERVAL = 60000;

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
    if (msg == "ON") {
      digitalWrite(RELAY_PIN, LOW);
      relayState = true;
      Serial.println("Releu MQTT: PORNIT");
    } else if (msg == "OFF") {
      digitalWrite(RELAY_PIN, HIGH);
      relayState = false;
      Serial.println("Releu MQTT: OPRIT");
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

      if (serialBuffer == "ON") {
        digitalWrite(RELAY_PIN, LOW);
        relayState = true;
        Serial.println("Releu: PORNIT");
      }
      else if (serialBuffer == "OFF") {
        digitalWrite(RELAY_PIN, HIGH);
        relayState = false;
        Serial.println("Releu: OPRIT");
      }
      else if (serialBuffer == "STATUS") {
        Serial.print("Releu: ");
        Serial.println(relayState ? "PORNIT" : "OPRIT");
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
float readCurrentRMS() {
  const int samples = 1500;

  // offset ADC
  float offset = 0;
  for (int i = 0; i < 800; i++) offset += analogRead(CURRENT_PIN);
  offset /= 800.0;

  // RMS
  float sum = 0;
  for (int i = 0; i < samples; i++) {
    float raw = analogRead(CURRENT_PIN);
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
  static float smooth = 0;
  smooth = smooth * 0.6 + current * 0.4;

  return smooth;
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
void sendMQTT(float voltage_rms, float current_rms, float power_W, double energy_kWh) {
  if (!client.connected()) reconnectMQTT();
  client.loop();

  char payload[256];
  snprintf(payload, sizeof(payload),
           "{\"voltage\":%.1f,\"current\":%.3f,\"power\":%.1f,\"energy\":%.5f,\"relay\":%s}",
           voltage_rms, current_rms, power_W, energy_kWh,
           relayState ? "true" : "false");

  Serial.print("MQTT publish: ");
  Serial.println(payload);

  client.publish(mqtt_topic_data, payload);
}

//-----------------------------------------------------------
// SETUP – CALIBRARE NOISE + WIFI + MQTT
//-----------------------------------------------------------
void setup() {
  Serial.begin(115200);

  analogSetPinAttenuation(CURRENT_PIN, ADC_11db);
  analogSetPinAttenuation(VOLTAGE_PIN, ADC_11db);

  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH);

  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);

  delay(500);

  Serial.println("Calibrare noise ACS712...");

  int base = 0;
  int baseSamples = 1500;

  // offset ADC
  for (int i = 0; i < baseSamples; i++) base += analogRead(CURRENT_PIN);
  base /= baseSamples;

  // variatie
  float noiseSum = 0;
  for (int i = 0; i < baseSamples; i++) {
    int val = analogRead(CURRENT_PIN);
    noiseSum += abs(val - base);
  }

  float noiseADC = noiseSum / baseSamples;

  // convertim in amperi
  float noiseVolt = noiseADC * (vRef / resolution) * dividerFactor;
  noiseOffset = noiseVolt / sensitivity;

  Serial.print("Noise detectat: ");
  Serial.println(noiseOffset, 5);

  delay(300);

  lastMillis = millis();
  lastMQTT = millis();
  Serial.println("Sistem pornit.");
}

//-----------------------------------------------------------
// LOOP PRINCIPAL
//-----------------------------------------------------------
void loop() {
  handleSerial();

  if (!client.connected()) reconnectMQTT();
  client.loop();

  float current_rms = readCurrentRMS();
  float voltage_rms = readVoltageRMS();
  float power_W = voltage_rms * current_rms;

  unsigned long now = millis();
  float deltaHours = (now - lastMillis) / 3600000.0;
  lastMillis = now;

  energy_kWh += (power_W * deltaHours) / 1000.0;

  Serial.print("U_RMS: ");
  Serial.print(voltage_rms, 1);
  Serial.print(" V | I_RMS: ");
  Serial.print(current_rms, 3);
  Serial.print(" A | P: ");
  Serial.print(power_W, 1);
  Serial.print(" W | kWh: ");
  Serial.println(energy_kWh, 5);

  // PUBLICĂ MQTT DOAR O DATĂ LA 1 MINUT
  if (millis() - lastMQTT >= MQTT_INTERVAL) {
    sendMQTT(voltage_rms, current_rms, power_W, energy_kWh);
    lastMQTT = millis();
  }

  delay(500);
}