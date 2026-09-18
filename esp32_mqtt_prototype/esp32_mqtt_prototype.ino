#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h> // Library untuk mengemas data menjadi JSON
#include <Wire.h>
#include "RTClib.h"

// ================= KONFIGURASI WIFI & MQTT =================
const char* ssid     = "SAUNG AWI";
const char* password = "kopisusu";

// Menggunakan EMQX Public Broker
const char* mqtt_server = "broker.emqx.io"; 
const int mqtt_port    = 1883;
const char* mqtt_user  = NULL; 
const char* mqtt_pass  = NULL; 

// Topik MQTT
const char* topic_publish   = "pindad/ac/logs";
const char* topic_subscribe = "pindad/ac/schedule";

// ================= DEKLARASI HARDWARE & PIN =================
RTC_DS3231 rtc;

const int RELAY1_PIN = 18;  // Kabel Kuning (Lampu Panel Bawah / AC 1)
const int RELAY2_PIN = 19;  // Kabel Hijau Muda (Lampu Panel Atas / AC 2)
const int SENSOR1_PIN = 34; // Kabel Pink (ACS712 Kiri / Beban 1)
const int SENSOR2_PIN = 35; // Kabel Abu-abu (ACS712 Kanan / Beban 2)

WiFiClient espClient;
PubSubClient client(espClient);

// Variabel Timer Pengiriman Data
unsigned long previousMillis = 0;
const long interval = 30000; // Mengirim telemetry setiap 30 detik (bisa diubah sesuai keinginan)

void setup() {
  Serial.begin(115200);
  
  // Konfigurasi pin Relay sebagai Output
  pinMode(RELAY1_PIN, OUTPUT);
  pinMode(RELAY2_PIN, OUTPUT);
  
  // Mengamankan kondisi awal (Active LOW: HIGH = Mati)
  digitalWrite(RELAY1_PIN, HIGH);
  digitalWrite(RELAY2_PIN, HIGH);
  
  // Inisialisasi RTC DS3231 (SDA = D21, SCL = D22)
  if (!rtc.begin()) {
    Serial.println("ERROR: RTC DS3231 tidak terdeteksi! Cek jalur kabel Oranye dan Ungu.");
    while (1); 
  }
  
  // Jika RTC kehilangan daya, setel ke waktu kompilasi PC
  if (rtc.lostPower()) {
    Serial.println("Mengatur ulang waktu RTC...");
    rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
  }
  
  setup_wifi();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);

  Serial.println("==================================================");
  Serial.println("ESP32 AC SCHEDULER & MQTT MONITORING ACTIVE");
  Serial.println("==================================================");
}

void setup_wifi() {
  delay(10);
  Serial.println();
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println("");
  Serial.println("WiFi connected");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
}

// Callback untuk menerima perintah dari Laravel via EMQX
void callback(char* topic, byte* payload, unsigned int length) {
  Serial.print("Message arrived on topic [");
  Serial.print(topic);
  Serial.print("]: ");
  
  String msg = "";
  for (int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }
  Serial.println(msg);

  // Parsing JSON perintah dari Laravel
  // Format diharapkan: {"relay": 1, "command": "ON"} atau {"relay": 2, "command": "OFF"}
  StaticJsonDocument<200> doc;
  DeserializationError error = deserializeJson(doc, msg);

  if (error) {
    Serial.print("deserializeJson() failed: ");
    Serial.println(error.c_str());
    return;
  }

  if (doc.containsKey("relay") && doc.containsKey("command")) {
    int relayNum = doc["relay"].as<int>();
    String command = doc["command"].as<String>();
    
    int targetPin = (relayNum == 1) ? RELAY1_PIN : RELAY2_PIN;
    
    // Active LOW: LOW = ON, HIGH = OFF
    if (command == "ON") {
      digitalWrite(targetPin, LOW);
      Serial.printf("Relay %d DINYALAKAN (LOW)\n", relayNum);
    } else if (command == "OFF") {
      digitalWrite(targetPin, HIGH);
      Serial.printf("Relay %d DIMATIKAN (HIGH)\n", relayNum);
    }
  }
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Attempting MQTT connection to EMQX...");
    String clientId = "ESP32Client-" + String(random(0, 0xffff), HEX);
    
    if (client.connect(clientId.c_str(), mqtt_user, mqtt_pass)) {
      Serial.println("connected");
      client.subscribe(topic_subscribe);
    } else {
      Serial.print("failed, rc=");
      Serial.print(client.state());
      Serial.println(" try again in 5 seconds");
      delay(5000);
    }
  }
}

// Fungsi pembacaan arus AC dari Anda
float hitungArusAC(int pinADC) {
  int nilaiMax = 0;
  int nilaiMin = 4095; 
  unsigned long start_time = millis();
  
  // Sampling gelombang selama 50 milidetik
  while ((millis() - start_time) < 50) {
    int bacaADC = analogRead(pinADC);
    if (bacaADC > nilaiMax) nilaiMax = bacaADC;
    if (bacaADC < nilaiMin) nilaiMin = bacaADC;
  }
  
  // Cetak nilai ADC untuk mencari tahu kesalahan wiring
  Serial.printf("[DEBUG PIN %d] Nilai ADC Min: %d | Max: %d | Selisih: %d\n", pinADC, nilaiMin, nilaiMax, (nilaiMax - nilaiMin));
  
  // Menghitung tegangan puncak ke puncak (ESP32 ADC 12-bit = 4095, VRef = 3.3V)
  float teganganPeakToPeak = ((nilaiMax - nilaiMin) * 3.3) / 4095.0;
  
  // Menghitung tegangan RMS
  float teganganRMS = (teganganPeakToPeak / 2.0) * 0.707;
  
  // Menghitung Arus RMS (Sensitivitas ACS712 5A = 185mV/A atau 0.185V/A)
  float arusRMS = teganganRMS / 0.185;
  
  // Filter noise agar tidak muncul angka bocor saat mesin benar-benar mati
  if (arusRMS < 0.10) {
    arusRMS = 0.00;
  }
  
  return arusRMS;
}

// Mengirim data beban ke MQTT Laravel
void publishLog(String active_ac, float current, String timeStr) {
  StaticJsonDocument<256> doc;
  doc["device_id"] = "ESP32_PINDAD_ROOM_1";
  doc["active_ac"] = active_ac;
  doc["current_ampere"] = current;
  doc["recorded_at"] = timeStr;

  char buffer[256];
  serializeJson(doc, buffer);

  Serial.print("Publishing to MQTT: ");
  Serial.println(buffer);
  client.publish(topic_publish, buffer);
}

void loop() {
  if (!client.connected()) {
    reconnect();
  }
  client.loop();

  unsigned long currentMillis = millis();

  // Pengiriman Telemetry Arus secara Berkala
  if (currentMillis - previousMillis >= interval) {
    previousMillis = currentMillis;
    
    // Ambil waktu dari RTC
    DateTime now = rtc.now();
    char timeBuffer[20];
    sprintf(timeBuffer, "%04d-%02d-%02d %02d:%02d:%02d", 
            now.year(), now.month(), now.day(), 
            now.hour(), now.minute(), now.second());
    String timestampStr = String(timeBuffer);
            
    // 1. BACA ARUS SENSOR (Sampling ~50ms)
    float arusBeban1 = hitungArusAC(SENSOR1_PIN);
    float arusBeban2 = hitungArusAC(SENSOR2_PIN);

    // 2. CETAK LOG KE SERIAL MONITOR
    Serial.print("[");
    Serial.print(timestampStr);
    Serial.print("] MONITORING: ");
    Serial.printf("Arus AC1: %.4f A  ||  Arus AC2: %.4f A\n", arusBeban1, arusBeban2);

    // 3. KIRIM DATA KE LARAVEL VIA MQTT EMQX
    // Mengirim status & beban AC 1 (Relay 1)
    String statusAc1 = (digitalRead(RELAY1_PIN) == LOW) ? "AC_1_ON" : "AC_1_OFF";
    publishLog(statusAc1, arusBeban1, timestampStr);

    // Mengirim status & beban AC 2 (Relay 2)
    String statusAc2 = (digitalRead(RELAY2_PIN) == LOW) ? "AC_2_ON" : "AC_2_OFF";
    publishLog(statusAc2, arusBeban2, timestampStr);
  }
}
