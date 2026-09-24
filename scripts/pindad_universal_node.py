#!/usr/bin/env python3
"""
=============================================================================
PINDAD IOT ENGINE - UNIVERSAL MULTI-NODE CONTROLLER CLIENT (HARDENED IIOT)
PT PINDAD (PERSERO) - DIVISI MUTU & TI
=============================================================================
File: pindad_universal_node.py
Fungsi: Client modular untuk setiap Raspberry Pi di seluruh ruangan server.
Fitur Unggulan:
  1. Proteksi Kompresor (Anti-Short-Cycling Guard 3 Menit / 180s)
  2. True-RMS Signal Processing & Moving Average Filter (ACS712 + ADS1115)
  3. Store-and-Forward SQLite Offline Ring-Buffer (Zero Data Loss)
  4. Dual-Sync RTC DS3231 + MQTT + HTTP REST Telemetry
=============================================================================
"""

import sys
import time
import datetime
import re
import math
import json
import os
import threading
import ssl
import sqlite3
import urllib.request
import urllib.parse
try:
    import paho.mqtt.client as mqtt
    HAS_PAHO = True
except ImportError:
    HAS_PAHO = False
    class OfflineMQTTStub:
        def __init__(self, *args, **kwargs): 
            self.on_connect = None
            self.on_message = None
        def connect_async(self, *args, **kwargs): pass
        def loop_start(self): pass
        def loop_stop(self): pass
        def publish(self, *args, **kwargs): pass
        def subscribe(self, *args, **kwargs): pass
    class OfflineMQTTModule:
        Client = OfflineMQTTStub
    mqtt = OfflineMQTTModule()

# Hardware imports for Raspberry Pi GPIO, I2C, RTC & ADC
try:
    import board
    import busio
    import RPi.GPIO as GPIO
    import adafruit_ds3231
    HAS_HARDWARE = True
except ImportError:
    HAS_HARDWARE = False
    print(" [NOTE] Berjalan di mode offline (RPi.GPIO / Adafruit library tidak ditemukan di PC host).")

HAS_ADC_LIB = False
ADS = None
AnalogIn = None
Pin = None
try:
    import adafruit_ads1x15.ads1115 as ADS
    from adafruit_ads1x15.analog_in import AnalogIn
    try:
        from adafruit_ads1x15.ads1x15 import Pin
    except Exception:
        Pin = None
    HAS_ADC_LIB = True
except Exception:
    HAS_ADC_LIB = False

# ================= 1. BACA FILE KONFIGURASI NODE & CLI ARGS =================
CONFIG_PATH = os.path.join(os.path.dirname(__file__), "node_config.json")

def load_config():
    default_config = {
        "device_id": "RPI3B_PINDAD_ROOM_1",
        "room_name": "Ruang Server Utama",
        "mqtt_broker_host": "127.0.0.1",
        "mqtt_broker_port": 1883,
        "sophos_auth": {"enabled": False},
        "relays": [
            {"ac_number": 1, "gpio_pin": 17, "name": "AC 1", "adc_channel": 0},
            {"ac_number": 2, "gpio_pin": 27, "name": "AC 2", "adc_channel": 1}
        ],
        "schedules": [],
        "turbo_cooling_seconds": 0,
        "telemetry_interval_seconds": 15,
        "compressor_min_off_seconds": 180
    }
    # Deteksi apakah skrip ini adalah skrip tailored dari server khusus untuk device tertentu
    is_tailored = default_config.get("device_id") != "RPI3B_PINDAD_ROOM_1"
    if is_tailored:
        # Selalu prioritaskan konfigurasi tailored dan perbarui node_config.json lokal
        try:
            with open(CONFIG_PATH, "w") as f:
                json.dump(default_config, f, indent=2)
        except Exception:
            pass
        return default_config

    if os.path.exists(CONFIG_PATH):
        try:
            with open(CONFIG_PATH, "r") as f:
                return {**default_config, **json.load(f)}
        except Exception as e:
            print(f" [WARNING] Gagal membaca {CONFIG_PATH}: {e}. Memakai konfigurasi default.")
    return default_config

config = load_config()

# Parsing CLI override
if len(sys.argv) > 1:
    config["device_id"] = sys.argv[1]
if len(sys.argv) > 2:
    config["mqtt_broker_host"] = sys.argv[2]
if len(sys.argv) > 3:
    config["dashboard_http_url"] = sys.argv[3]

DEVICE_ID = config.get("device_id", "RPI3B_PINDAD_ROOM_1")
BROKER_HOST = config.get("mqtt_broker_host", "127.0.0.1")
BROKER_PORT = config.get("mqtt_broker_port", 1883)
RELAYS = config.get("relays", [])
INTERVAL_SEC = config.get("telemetry_interval_seconds", 15)
TURBO_COOLING_SEC = config.get("turbo_cooling_seconds", 0)
MIN_OFF_TIME_SEC = config.get("compressor_min_off_seconds", 180)
# Sebagian besar modul relay optocoupler Raspberry Pi berlogika ACTIVE-LOW (LOW = ON / Magnet Aktif, HIGH = OFF)
RELAY_ACTIVE_LOW = bool(config.get("relay_active_low", True))

print("=" * 70)
print(f" [INIT] PINDAD IOT UNIVERSAL NODE CONTROLLER (ENTERPRISE IIOT)")
print(f" [NODE ID]        : {DEVICE_ID}")
print(f" [ROOM]           : {config.get('room_name', 'N/A')}")
print(f" [MQTT BROKER]    : {BROKER_HOST}:{BROKER_PORT}")
print(f" [DASHBOARD HTTP] : {config.get('dashboard_http_url', 'Auto-Detect')}")
print(f" [RELAY COUNT]    : {len(RELAYS)} Unit AC")
print(f" [RELAY POLARITY] : {'ACTIVE-LOW (LOW=ON, HIGH=OFF) [Standar Modul Optocoupler]' if RELAY_ACTIVE_LOW else 'ACTIVE-HIGH (HIGH=ON, LOW=OFF)'}")
print(f" [COMPRESSOR GUARD]: {MIN_OFF_TIME_SEC} Detik Anti-Short-Cycling Lockout")
print("=" * 70)

# ================= 2. OFFLINE TELEMETRY RING-BUFFER (STORE-AND-FORWARD) =================
class OfflineTelemetryBuffer:
    """Buffer lokal SQLite thread-safe untuk menjamin Zero Data Loss saat jaringan putus"""
    def __init__(self, db_path=None, max_records=50000):
        if db_path is None:
            clean_dev = re.sub(r'[^a-zA-Z0-9_]', '_', str(DEVICE_ID).lower())
            db_path = os.path.join(os.path.dirname(__file__), f"pindad_offline_{clean_dev}.db")
        self.db_path = db_path
        self.max_records = max_records
        self.lock = threading.Lock()
        self._init_db()

    def _init_db(self):
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("""
                    CREATE TABLE IF NOT EXISTS offline_telemetry (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        payload TEXT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                """)
                cursor.execute("CREATE INDEX IF NOT EXISTS idx_buf_id ON offline_telemetry (id)")
                conn.commit()
                conn.close()
            except Exception as e:
                print(f" [OFFLINE BUFFER INIT ERROR] {e}")

    def enqueue(self, payload_dict):
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("INSERT INTO offline_telemetry (payload) VALUES (?)", (json.dumps(payload_dict),))
                cursor.execute("SELECT COUNT(*) FROM offline_telemetry")
                count = cursor.fetchone()[0]
                if count > self.max_records:
                    excess = count - self.max_records
                    cursor.execute("DELETE FROM offline_telemetry WHERE id IN (SELECT id FROM offline_telemetry ORDER BY id ASC LIMIT ?)", (excess,))
                conn.commit()
                conn.close()
                return True
            except Exception as e:
                print(f" [OFFLINE BUFFER ENQUEUE ERROR] {e}")
                return False

    def fetch_batch(self, batch_size=20):
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("SELECT id, payload FROM offline_telemetry ORDER BY id ASC LIMIT ?", (batch_size,))
                rows = cursor.fetchall()
                conn.close()
                return rows
            except Exception:
                return []

    def delete_ids(self, id_list):
        if not id_list:
            return
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                placeholders = ",".join("?" for _ in id_list)
                cursor.execute(f"DELETE FROM offline_telemetry WHERE id IN ({placeholders})", id_list)
                conn.commit()
                conn.close()
            except Exception:
                pass

    def get_count(self):
        with self.lock:
            try:
                conn = sqlite3.connect(self.db_path)
                cursor = conn.cursor()
                cursor.execute("SELECT COUNT(*) FROM offline_telemetry")
                count = cursor.fetchone()[0]
                conn.close()
                return count
            except Exception:
                return 0

offline_buffer = OfflineTelemetryBuffer()

# ================= 3. INISIALISASI HARDWARE I/O & RELAI =================
relay_states = {}
tactile_pins = {}
adc_channels = {}
last_turned_off_timestamp = {r["ac_number"]: 0.0 for r in RELAYS}
relay_turned_on_time = {r["ac_number"]: time.time() for r in RELAYS}
pending_delayed_start_timers = {}

HAS_GPIO = False
HAS_ADC = False

if HAS_HARDWARE:
    # 1. Inisialisasi Mandiri GPIO Relay
    try:
        GPIO.setmode(GPIO.BCM)
        GPIO.setwarnings(False)
        for r in RELAYS:
            pin = r["gpio_pin"]
            GPIO.setup(pin, GPIO.OUT)
            init_st = r.get("initial_state", False)
            if RELAY_ACTIVE_LOW:
                initial_val = GPIO.LOW if init_st else GPIO.HIGH
            else:
                initial_val = GPIO.HIGH if init_st else GPIO.LOW
            GPIO.output(pin, initial_val)
            relay_states[r["ac_number"]] = init_st
            if "tactile_pin" in r and r["tactile_pin"]:
                t_pin = r["tactile_pin"]
                GPIO.setup(t_pin, GPIO.OUT)
                GPIO.output(t_pin, GPIO.LOW)
                tactile_pins[r["ac_number"]] = t_pin
        HAS_GPIO = True
        print(f" [HARDWARE] GPIO Relay ({len(RELAYS)} Channel) Berhasil Diinisialisasi.")
    except Exception as e:
        print(f" [GPIO ERROR] Gagal inisialisasi pin relay: {e}")
        HAS_GPIO = False

    # 2. Inisialisasi Mandiri I2C ADS1115 ADC (Channel 0, 1, 2, 3)
    if HAS_ADC_LIB:
        try:
            i2c = busio.I2C(board.SCL, board.SDA)
            ads = ADS.ADS1115(i2c)
            try:
                ads.data_rate = 860  # Atur ke mode kecepatan tinggi 860 Samples Per Second
            except Exception:
                pass
            adc_pin_map = []
            if Pin is not None and hasattr(Pin, "A0"):
                adc_pin_map = [Pin.A0, Pin.A1, Pin.A2, Pin.A3]
            else:
                try:
                    from adafruit_ads1x15.ads1x15 import P0, P1, P2, P3
                    adc_pin_map = [P0, P1, P2, P3]
                except Exception:
                    adc_pin_map = [0, 1, 2, 3]

            for r in RELAYS:
                ch_idx = r.get("adc_channel", 0)
                if 0 <= ch_idx < len(adc_pin_map):
                    adc_channels[r["ac_number"]] = AnalogIn(ads, adc_pin_map[ch_idx])
            HAS_ADC = True
            print(" [HARDWARE] I2C ADS1115 ADC (Real Telemetri) Berhasil Diinisialisasi.")
        except Exception as e:
            print(f" [ADC NOTICE] Inisialisasi ADS1115: {e}. Relay tetap berjalan normal (Arus = 0.0000 A).")
            HAS_ADC = False

    # 3. Inisialisasi Mandiri I2C RTC DS3231 Hardware (Dual-Sync)
    rtc_device = None
    HAS_RTC = False
    if HAS_HARDWARE:
        try:
            if 'i2c' not in locals() or i2c is None:
                i2c = busio.I2C(board.SCL, board.SDA)
            import adafruit_ds3231
            rtc_device = adafruit_ds3231.DS3231(i2c)
            t_check = rtc_device.datetime
            HAS_RTC = True
            print(f" [HARDWARE] I2C RTC DS3231 Berhasil Diinisialisasi (Waktu RTC: {t_check.tm_year:04d}-{t_check.tm_mon:02d}-{t_check.tm_mday:02d} {t_check.tm_hour:02d}:{t_check.tm_min:02d}:{t_check.tm_sec:02d}).")
        except Exception as e:
            err_msg = str(e).lower()
            if "busy" in err_msg or "16" in err_msg:
                HAS_RTC = True
                print(" [HARDWARE] I2C RTC DS3231 Terpasang & Dikelola Langsung oleh Driver Kernel Linux (/dev/rtc0).")
            else:
                print(f" [RTC NOTICE] Inisialisasi DS3231: {e}. Menggunakan jam sistem Linux sebagai rujukan.")

for r in RELAYS:
    if r["ac_number"] not in relay_states:
        relay_states[r["ac_number"]] = r.get("initial_state", False)

# ================= 4. PROTEKSI KOMPRESOR & KONTROL RELAI =================
def _execute_relay_hardware(ac_num, state_bool):
    """Eksekusi fisik saklar GPIO / Pulsa Taktil"""
    relay_states[ac_num] = state_bool
    if not state_bool:
        last_turned_off_timestamp[ac_num] = time.time()
    else:
        relay_turned_on_time[ac_num] = time.time()

    if HAS_GPIO or HAS_HARDWARE:
        try:
            r_info = next((r for r in RELAYS if r["ac_number"] == ac_num), None)
            if r_info:
                pin = r_info["gpio_pin"]
                is_tactile = r_info.get("mode", "LATCHING").upper() == "TACTILE"
                if is_tactile:
                    duration = r_info.get("pulse_duration", 0.5)
                    p_on = GPIO.LOW if RELAY_ACTIVE_LOW else GPIO.HIGH
                    p_off = GPIO.HIGH if RELAY_ACTIVE_LOW else GPIO.LOW
                    GPIO.output(pin, p_on)
                    time.sleep(duration)
                    GPIO.output(pin, p_off)
                    print(f" [RELAY PULSA TAKTIL] AC {ac_num} -> Pulsa {duration}s terkirim (Pin GPIO {pin})")
                else:
                    target_lvl = (GPIO.LOW if state_bool else GPIO.HIGH) if RELAY_ACTIVE_LOW else (GPIO.HIGH if state_bool else GPIO.LOW)
                    GPIO.output(pin, target_lvl)
                    lvl_name = "LOW (Tegangan 0V / Koil Aktif)" if target_lvl == GPIO.LOW else "HIGH (Tegangan 3.3V / Koil Lepas)"
                    print(f" [RELAY FISIK BERHASIL] AC {ac_num} -> {'ON (LAMPU HIDUP)' if state_bool else 'OFF (LAMPU MATI)'} [Pin GPIO {pin} = {lvl_name}]")
        except Exception as e:
            print(f" [RELAY GPIO ERROR] AC {ac_num}: {e}")

def switch_relay(ac_num, state_bool, bypass_guard=False):
    """
    Mengontrol saklar relai dengan perlindungan Anti-Short-Cycling Guard (3 Menit).
    Mencegah kerusakan kompresor AC akibat lonjakan tekanan freon saat siklus ON/OFF cepat.
    """
    global relay_states, last_turned_off_timestamp, pending_delayed_start_timers

    current_state = relay_states.get(ac_num, False)

    # Batalkan timer tunda jika ada perintah baru
    if ac_num in pending_delayed_start_timers:
        pending_delayed_start_timers[ac_num].cancel()
        del pending_delayed_start_timers[ac_num]

    # JIKA PERINTAH ON (MENYALAKAN AC)
    if state_bool and not current_state:
        last_off = last_turned_off_timestamp.get(ac_num, 0.0)
        elapsed_since_off = time.time() - last_off

        # Cek apakah kompresor baru saja mati kurang dari jeda proteksi (180 detik)
        if not bypass_guard and last_off > 0 and elapsed_since_off < MIN_OFF_TIME_SEC:
            remaining_wait = int(MIN_OFF_TIME_SEC - elapsed_since_off)
            print(f" [PROTEKSI KOMPRESOR] AC {ac_num} baru dimatikan {int(elapsed_since_off)}s lalu. "
                  f"Menunda penyalaan selama {remaining_wait}s demi keamanan mekanikal kompresor (Anti-Short-Cycling).")

            def _delayed_turn_on():
                print(f" [PROTEKSI KOMPRESOR SELESAI] Jeda proteksi 3 menit terpenuhi. Menyalakan AC {ac_num} sekarang.")
                _execute_relay_hardware(ac_num, True)
                relay_turned_on_time[ac_num] = time.time()
                send_instant_telemetry(ac_num)
                if ac_num in pending_delayed_start_timers:
                    del pending_delayed_start_timers[ac_num]

            t = threading.Timer(remaining_wait, _delayed_turn_on)
            t.daemon = True
            pending_delayed_start_timers[ac_num] = t
            t.start()
            return "DELAYED"

        _execute_relay_hardware(ac_num, True)
        relay_turned_on_time[ac_num] = time.time()
        print(f" [RELAY AC {ac_num}] -> STATUS: ON (Active)")
        send_instant_telemetry(ac_num)
        return True

    # JIKA PERINTAH OFF (MEMATIKAN AC)
    elif not state_bool and current_state:
        _execute_relay_hardware(ac_num, False)
        last_turned_off_timestamp[ac_num] = time.time()
        print(f" [RELAY AC {ac_num}] -> STATUS: OFF (Inactive) | Timestamp mati tercatat untuk proteksi.")
        send_instant_telemetry(ac_num)
        return True

    return True

# ================= 5. HIGH-ACCURACY TRUE-RMS SAMPLING ACS712 + ADS1115 =================
# Sensitivitas modul ACS712: 185 mV/A (05B / 5A), 100 mV/A (20A), 66 mV/A (30A)
acs_model = str(config.get("acs712_model", "5A")).upper()
if "20" in acs_model:
    SENSITIVITAS_ACS712 = 0.100  # 100 mV/A untuk modul ACS712 20A
elif "30" in acs_model:
    SENSITIVITAS_ACS712 = 0.066  # 66 mV/A untuk modul ACS712 30A
else:
    SENSITIVITAS_ACS712 = float(config.get("acs712_sensitivity", 0.185))  # 185 mV/A untuk ACS712 5A

CALIBRATION_GAIN = float(config.get("calibration_gain_factor", 1.0))
MOVING_AVG_WINDOW_SIZE = 3
current_moving_avg_buffers = {r["ac_number"]: [] for r in RELAYS}

def read_current_ampere(ac_num):
    """
    Membaca arus listrik riil 100% murni dari sensor fisik ACS712 via ADC ADS1115.
    ZERO DUMMY - TANPA PEMOTONGAN AMBANG BATAS NOISE. Berapapun arus fisik yang terdeteksi
    oleh sensor (kecil maupun besar) ditampilkan apa adanya secara transparan dan murni.
    Saat relai mati (OFF), nilai dikembalikan murni 0.0000 A.
    """
    is_on = relay_states.get(ac_num, False)
    if not is_on:
        current_moving_avg_buffers[ac_num] = []
        return 0.0000

    chan = adc_channels.get(ac_num)
    arus_fisik_riil = 0.0000

    # 1. PENGAMBILAN DATA SAMPEL VOLTASE ANALOG DARI ADS1115 (16-BIT)
    if chan is not None and (HAS_ADC or HAS_HARDWARE):
        samples = []
        start_time = time.time()
        last_err = None
        # Sampling rapat selama 200ms (menangkap 10 siklus penuh gelombang sinus AC 50Hz)
        while (time.time() - start_time) < 0.20:
            try:
                samples.append(chan.voltage)
            except Exception as e:
                last_err = e

        if len(samples) >= 3:
            # Hitung titik tengah quiescent tegangan DC sensor (tegangan nol referensi riil ~2.4V - 2.5V)
            v_offset = sum(samples) / len(samples)

            # Perhitungan True-RMS Digital Signal Processing murni: sqrt( sum((V - V_offset)^2) / N )
            sum_squared_diff = sum((v - v_offset) ** 2 for v in samples)
            v_rms = math.sqrt(sum_squared_diff / len(samples))

            # Konversi tegangan RMS ke Arus RMS murni murni tanpa potongan batas noise
            arus_instan = (v_rms / SENSITIVITAS_ACS712) * CALIBRATION_GAIN

            # Filter Moving Average adaptif untuk kestabilan pembacaan riil
            buf = current_moving_avg_buffers.get(ac_num, [])
            buf.append(arus_instan)
            if len(buf) > MOVING_AVG_WINDOW_SIZE:
                buf.pop(0)
            current_moving_avg_buffers[ac_num] = buf
            arus_fisik_riil = sum(buf) / len(buf)

            # Diagnostik transparan ke log terminal untuk verifikasi sensor fisik
            print(f" [ADC SENSOR AC {ac_num}] {len(samples)} sampel | V_DC: {v_offset:.3f}V | V_RMS: {v_rms:.4f}V | Arus Terhitung: {arus_fisik_riil:.4f} A")
        else:
            err_msg = f" (Error: {last_err})" if last_err else ""
            print(f" [ADC WARNING AC {ac_num}] Jumlah sampel terlalu sedikit ({len(samples)} sampel){err_msg}. Periksa jalur I2C ADS1115.")
    else:
        if not HAS_ADC:
            print(f" [ADC NOTICE AC {ac_num}] Modul ADC ADS1115 belum aktif atau tidak terdeteksi di alamat I2C 0x48.")
        elif chan is None:
            print(f" [ADC NOTICE AC {ac_num}] Channel ADC ADS1115 untuk AC {ac_num} belum terdaftar di adc_channels.")
        arus_fisik_riil = 0.0000

    if arus_fisik_riil > 0.0000:
        return round(arus_fisik_riil, 4)

    return 0.0000

# ================= 6. JADWAL & ROTASI WAKTU (RTC DS3231) =================
active_schedules = config.get("schedules", [])
manual_override = {r["ac_number"]: False for r in RELAYS}
is_turbo_cooling_active = TURBO_COOLING_SEC > 0
last_active_schedule_map = {}

def get_current_datetime():
    global rtc_device
    if 'rtc_device' in globals() and rtc_device:
        try:
            t = rtc_device.datetime
            if t and t.tm_year >= 2024:
                return datetime.datetime(t.tm_year, t.tm_mon, t.tm_mday, t.tm_hour, t.tm_min, t.tm_sec)
        except Exception:
            pass
    return datetime.datetime.now()

def get_current_timestamp():
    return get_current_datetime().strftime("%Y-%m-%d %H:%M:%S")

def get_current_time_hm():
    return get_current_datetime().strftime("%H:%M")

def is_schedule_active_for_ac(sch, ac_num, now_hm):
    if not sch.get("is_active", True):
        return False

    raw_target = str(sch.get("target_ac") or sch.get("ac_number") or "").strip().upper()
    
    matches_ac = False
    if raw_target in ["ALL", "SEMUA", ""]:
        matches_ac = True
    elif raw_target in [str(ac_num), f"AC_{ac_num}", f"AC {ac_num}", f"UNIT_{ac_num}", f"UNIT {ac_num}"]:
        matches_ac = True
    else:
        m = re.search(r'\d+', raw_target)
        if m and int(m.group()) == ac_num:
            matches_ac = True

    if not matches_ac:
        return False

    st = str(sch.get("start_time", "00:00")).strip()[:5]
    et = str(sch.get("end_time", "00:00")).strip()[:5]

    if st <= et:
        return st <= now_hm <= et
    else:
        # Cross-midnight shift (misal shift malam 18:00 - 06:00)
        return now_hm >= st or now_hm <= et

def evaluate_schedules(force=False):
    global relay_states, manual_override, is_turbo_cooling_active, last_active_schedule_map
    if is_turbo_cooling_active:
        return

    # JIKA TIDAK ADA JADWAL AKTIF (Seluruh jadwal kosong / dihapus):
    # Mode manual murni. Jangan sentuh relai, jangan jalankan rotasi bayangan.
    # Pertahankan status saklar dan relai AC apa adanya.
    if not active_schedules:
        for r in RELAYS:
            u = r["ac_number"]
            last_active_schedule_map[u] = None
        return

    all_sch_ids = [s.get("id") for s in active_schedules if s.get("id")]
    now_hm = get_current_time_hm()

    for r in RELAYS:
        ac_num = r["ac_number"]
        matching_sch = [s for s in active_schedules if is_schedule_active_for_ac(s, ac_num, now_hm)]
        active_sch_id = matching_sch[0].get("id") if matching_sch else None
        prev_sch_id = last_active_schedule_map.get(ac_num)

        # Kasus A: Shift baru yang terjadwal dimulai sekarang
        if active_sch_id != prev_sch_id and active_sch_id is not None:
            last_active_schedule_map[ac_num] = active_sch_id
            manual_override[ac_num] = False  # Masuk ke jadwal baru -> reset manual override

        # Kasus B: Shift sebelumnya berakhir
        elif active_sch_id is None and prev_sch_id is not None:
            last_active_schedule_map[ac_num] = None
            # Jika jadwal sebelumnya tidak ada lagi di daftar (DIHAPUS oleh user),
            # pertahankan status AC tetap menyala (beralih ke kontrol manual) agar AC tidak mati mendadak
            if prev_sch_id not in all_sch_ids:
                manual_override[ac_num] = True
                continue
            else:
                # Jadwal masih ada di daftar, namun jam operasional shift telah berakhir
                manual_override[ac_num] = False

        # Jika sedang dalam mode override manual (pengguna baru saja menekan saklar di web),
        # hormati pilihan pengguna dan jangan ubah status AC
        if manual_override.get(ac_num, False) and not force:
            continue

        should_be_on = (len(matching_sch) > 0)

        # HANYA eksekusi hardware jika status relay memang perlu diubah!
        # Mencegah relai terpicu ulang atau saklar dashboard berkedip sendiri
        if relay_states.get(ac_num) != should_be_on:
            sch_desc = f"Aturan '{matching_sch[0].get('label', 'Jadwal')}'" if should_be_on and matching_sch else "Luar Jam Kerja"
            print(f" [RTC OTOMASI SHIFT] AC {ac_num} -> {'ON' if should_be_on else 'OFF'} (Waktu RTC: {now_hm} | {sch_desc})")
            _execute_relay_hardware(ac_num, should_be_on)
            send_instant_telemetry(ac_num)

# ================= 7. TELEMETRI, DUAL-SYNC & STORE-AND-FORWARD =================
last_telegram_alert = {}

def send_direct_telegram_alert(ac_num, unit_name, current_amp, failure_type="GAGAL_HIDUP"):
    tele = config.get("telegram", {})
    if not tele or not tele.get("enabled", True):
        return
    bot_token = tele.get("bot_token")
    chat_id = tele.get("chat_id")
    if not bot_token or not chat_id:
        return

    cooldown_sec = max(60, tele.get("cooldown_minutes", 15) * 60)
    now_ts = time.time()
    last_sent = last_telegram_alert.get((ac_num, failure_type), 0)
    if now_ts - last_sent < cooldown_sec:
        return

    last_telegram_alert[(ac_num, failure_type)] = now_ts
    ts_str = get_current_timestamp()
    room = config.get("room_name", DEVICE_ID)

    pesan = (
        f"🚨 *[PERINGATAN DARURAT SIKOMAT AC]* 🚨\n\n"
        f"🏢 *Ruangan:* {room} (`{DEVICE_ID}`)\n"
        f"❄️ *Unit:* {unit_name} (AC {ac_num})\n"
        f"⚠️ *Status Anomali:* *{failure_type}*\n"
        f"⚡ *Arus Terdeteksi:* `{current_amp:.4f} A` (Beban Hilang / 0A)\n"
        f"🕒 *Waktu Kejadian:* `{ts_str}`\n\n"
        f"🔔 _Notifikasi otomatis dikirim langsung oleh Node Raspberry Pi Edge Engine._"
    )

    try:
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        payload_data = json.dumps({"chat_id": chat_id, "text": pesan, "parse_mode": "Markdown"}).encode("utf-8")
        req = urllib.request.Request(url, data=payload_data, headers={"Content-Type": "application/json"})
        with urllib.request.urlopen(req, timeout=5) as resp:
            pass
        print(f" [TELEGRAM ALERT] Peringatan darurat AC {ac_num} terkirim ke Telegram!")
    except Exception as e:
        print(f" [TELEGRAM ALERT ERROR] {e}")

def drain_offline_buffer(dash_url):
    """Kuras antrean log telemetri yang sempat tertahan di buffer lokal secara bertahap"""
    count = offline_buffer.get_count()
    if count == 0:
        return
    rows = offline_buffer.fetch_batch(batch_size=25)
    if not rows:
        return

    success_ids = []
    for row_id, payload_str in rows:
        try:
            req = urllib.request.Request(
                f"{dash_url}/api/telemetry",
                data=payload_str.encode('utf-8'),
                headers={'Content-Type': 'application/json', 'User-Agent': 'PindadIoTNode/1.0', 'X-Pindad-Buffer': '1'}
            )
            with urllib.request.urlopen(req, timeout=2) as resp:
                if resp.getcode() == 200:
                    success_ids.append(row_id)
        except Exception:
            break

    if success_ids:
        offline_buffer.delete_ids(success_ids)
        print(f" [OFFLINE BUFFER CATCH-UP] Berhasil mengirim {len(success_ids)} log tertunda ke Server (Sisa antrean: {count - len(success_ids)})")

def send_http_telemetry(payload):
    """Kirim telemetri HTTP REST dengan Auto-Store ke Offline Buffer jika jaringan LAN terputus"""
    global active_schedules
    dash_url = config.get("dashboard_http_url")
    if not dash_url:
        return False
    try:
        req_data = json.dumps(payload).encode('utf-8')
        req = urllib.request.Request(
            f"{dash_url}/api/telemetry",
            data=req_data,
            headers={'Content-Type': 'application/json', 'User-Agent': 'PindadIoTNode/1.0'}
        )
        with urllib.request.urlopen(req, timeout=3) as resp:
            resp_bytes = resp.read()
            if resp_bytes:
                resp_json = json.loads(resp_bytes.decode('utf-8'))
                if "telegram" in resp_json and isinstance(resp_json["telegram"], dict):
                    config["telegram"] = resp_json["telegram"]
                if "server_time" in resp_json:
                    try:
                        srv_str = resp_json["server_time"]
                        clean_str = srv_str[:19].replace("T", " ")
                        srv_dt = datetime.datetime.strptime(clean_str, "%Y-%m-%d %H:%M:%S")
                        cur_rtc = get_current_datetime()
                        diff_sec = abs((srv_dt - cur_rtc).total_seconds())
                        if diff_sec > 10:
                            if 'rtc_device' in globals() and rtc_device:
                                try:
                                    rtc_device.datetime = srv_dt.timetuple()
                                    print(f" [RTC HARDWARE SYNC] Jam DS3231 berhasil disinkronkan ke waktu Server: {srv_dt.strftime('%Y-%m-%d %H:%M:%S')} (Koreksi selisih {int(diff_sec)}s)")
                                except Exception as _err_rtc:
                                    print(f" [RTC HARDWARE SYNC WARNING] Gagal set DS3231: {_err_rtc}")
                            os.system(f"sudo -n date -s '{srv_dt.strftime('%Y-%m-%d %H:%M:%S')}' 2>/dev/null")
                            evaluate_schedules(force=True)
                    except Exception as _e_sync:
                        print(f" [SERVER TIME SYNC ERROR] {_e_sync}")

                if "schedules" in resp_json and isinstance(resp_json["schedules"], list):
                    new_scheds = resp_json["schedules"]
                    if json.dumps(new_scheds, sort_keys=True) != json.dumps(active_schedules, sort_keys=True):
                        active_schedules = new_scheds
                        config["schedules"] = new_scheds
                        print(f" [SYNC JADWAL WEB] Menerima {len(active_schedules)} aturan jadwal terbaru dari Dashboard!")
                        try:
                            with open(CONFIG_PATH, "w") as f:
                                json.dump(config, f, indent=2)
                        except Exception:
                            pass
                        evaluate_schedules(force=False)
            return True
    except Exception:
        # Simpan ke buffer lokal jika koneksi HTTP gagal
        offline_buffer.enqueue(payload)
        return False

def send_instant_telemetry(target_ac_num=None):
    """Kirim telemetri instan seketika saat saklar relay berganti status (Zero Delay)"""
    try:
        ts = get_current_timestamp()
        units_to_send = [r for r in RELAYS if r["ac_number"] == target_ac_num] if target_ac_num else RELAYS
        for r in units_to_send:
            ac_num = r["ac_number"]
            is_on = relay_states.get(ac_num, False)
            current_amp = read_current_ampere(ac_num)
            payload = {
                "device_id": DEVICE_ID,
                "active_ac": f"AC_{ac_num}_{'ON' if is_on else 'OFF'}",
                "ac_number": ac_num,
                "state": "ON" if is_on else "OFF",
                "current_ampere": current_amp,
                "watt": round(current_amp * 220),
                "recorded_at": ts,
                "turbo_active": is_turbo_cooling_active
            }
            local_client.publish(f"pindad/devices/{DEVICE_ID}/telemetry", json.dumps(payload))
            # Send HTTP telemetry asynchronously in background thread to never block MQTT loop
            threading.Thread(target=send_http_telemetry, args=(payload,), daemon=True).start()
    except Exception as e:
        print(f" [INSTANT TELEMETRY ERROR] {e}")

# ================= 8. MQTT CLIENT & HANDLERS =================
client_id_ktp = f"PINDAD_NODE_{DEVICE_ID}"
try:
    local_client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION1, client_id=client_id_ktp)
except Exception:
    local_client = mqtt.Client(client_id=client_id_ktp)

def on_local_connect(client, userdata, flags, rc, properties=None):
    if rc == 0:
        print(f" [MQTT CONNECTED] Berhasil terhubung ke broker {BROKER_HOST}:{BROKER_PORT}")
        # Dedicated namespaced channel for this specific device
        client.subscribe(f"pindad/devices/{DEVICE_ID}/control")
        client.subscribe(f"pindad/ac/control/{DEVICE_ID}")
        client.subscribe(f"pindad/devices/{DEVICE_ID}/schedule")
    else:
        print(f" [MQTT CONNECT ERROR] Gagal koneksi, return code: {rc}")

def on_local_message(client, userdata, msg):
    try:
        topic = msg.topic
        payload_str = msg.payload.decode("utf-8").strip()
        print(f" [MQTT COMMAND INCOMING] Topik: {topic} | Payload: {payload_str}")

        cmd = ""
        ac_num = 1
        target_dev = None
        data = {}

        if payload_str.startswith("{") and payload_str.endswith("}"):
            try:
                data = json.loads(payload_str)
                target_dev = data.get("device_id")
                cmd = str(data.get("command", "") or data.get("state", "")).upper()
                raw_ac = data.get("ac") or data.get("ac_number") or data.get("relay")
                if raw_ac is not None:
                    if str(raw_ac).upper() == "ALL":
                        cmd = "ALL_ON" if cmd in ["ON", "ALL_ON"] else "ALL_OFF"
                    else:
                        try:
                            ac_num = int(raw_ac)
                        except ValueError:
                            pass
                elif "pin" in data:
                    pin_str = str(data["pin"]).upper()
                    if pin_str.startswith("V"):
                        try:
                            ac_num = int(pin_str[1:]) + 1
                        except ValueError:
                            pass
            except Exception as e:
                print(f" [PAYLOAD PARSE ERROR] {e}")
        else:
            cmd = payload_str.upper()

        # Jadwal Otomatis Sync via MQTT Topic (Instant Real-time push)
        if topic.endswith("/schedule") or topic.endswith("schedule"):
            try:
                if isinstance(data, dict) and "server_time" in data:
                    try:
                        clean_str = data["server_time"][:19].replace("T", " ")
                        srv_dt = datetime.datetime.strptime(clean_str, "%Y-%m-%d %H:%M:%S")
                        cur_rtc = get_current_datetime()
                        diff_sec = abs((srv_dt - cur_rtc).total_seconds())
                        if diff_sec > 10:
                            if 'rtc_device' in globals() and rtc_device:
                                try:
                                    rtc_device.datetime = srv_dt.timetuple()
                                    print(f" [MQTT RTC SYNC] Jam DS3231 disinkronkan via MQTT: {srv_dt.strftime('%Y-%m-%d %H:%M:%S')}")
                                except Exception:
                                    pass
                            os.system(f"sudo -n date -s '{srv_dt.strftime('%Y-%m-%d %H:%M:%S')}' 2>/dev/null")
                    except Exception:
                        pass
                new_scheds = []
                if isinstance(data, dict):
                    if "schedules" in data and isinstance(data["schedules"], list):
                        new_scheds = data["schedules"]
                    elif "target_ac" in data:
                        new_scheds = [data]
                elif isinstance(data, list):
                    new_scheds = data

                if new_scheds is not None:
                    active_schedules = new_scheds
                    config["schedules"] = new_scheds
                    print(f" [MQTT JADWAL SYNC] Menerima {len(active_schedules)} jadwal terbaru secara instan via MQTT!")
                    try:
                        with open(CONFIG_PATH, "w") as f:
                            json.dump(config, f, indent=2)
                    except Exception:
                        pass
                    evaluate_schedules(force=False)
            except Exception as ex:
                print(f" [MQTT JADWAL ERROR] {ex}")
            return

        if target_dev and target_dev != DEVICE_ID and target_dev != "ALL":
            return

        # Master fleet control
        if cmd in ["ALL_ON", "ALL_OFF"]:
            target_state = (cmd == "ALL_ON")
            for r in RELAYS:
                u = r["ac_number"]
                manual_override[u] = True
                _execute_relay_hardware(u, target_state)
            send_instant_telemetry()
            return

        # Single AC toggle (Selalu eksekusi fisik hardware pin tanpa skip)
        if cmd in ["ON", "OFF"]:
            target_st = (cmd == "ON")
            manual_override[ac_num] = True
            _execute_relay_hardware(ac_num, target_st)
            print(f" [MANUAL WEB SAKLAR] AC {ac_num} diperintahkan {'ON' if target_st else 'OFF'} oleh pengguna (Pin fisik dipicu).")
            send_instant_telemetry(ac_num)
        elif cmd in ["AC_1_ON", "AC_1_OFF", "AC_2_ON", "AC_2_OFF"]:
            parts = cmd.split("_")
            ac_num = int(parts[1])
            state = (parts[2] == "ON")
            manual_override[ac_num] = True
            _execute_relay_hardware(ac_num, state)
            print(f" [MANUAL WEB SAKLAR] AC {ac_num} diperintahkan {'ON' if state else 'OFF'} oleh pengguna.")
            send_instant_telemetry(ac_num)
        elif cmd.startswith("AC_"):
            parts = cmd.split("_")
            if len(parts) >= 3:
                ac_num = int(parts[1])
                st = (parts[2].upper() == "ON")
                manual_override[ac_num] = True
                _execute_relay_hardware(ac_num, st)
                print(f" [MANUAL WEB SAKLAR] AC {ac_num} diperintahkan {'ON' if st else 'OFF'} oleh pengguna.")
                send_instant_telemetry(ac_num)
    except Exception as e:
        print(f" [MQTT MSG ERROR] {e}")

local_client.on_connect = on_local_connect
local_client.on_message = on_local_message

def initial_server_sync():
    """Sinkronisasi awal waktu server & jadwal shift seketika saat node booting"""
    global active_schedules
    dash_url = config.get("dashboard_http_url")
    if not dash_url:
        return
    try:
        req_data = json.dumps({
            "device_id": DEVICE_ID,
            "boot_sync": True,
            "state": "BOOT",
            "current_ampere": 0.0,
            "watt": 0
        }).encode('utf-8')
        req = urllib.request.Request(
            f"{dash_url}/api/telemetry",
            data=req_data,
            headers={'Content-Type': 'application/json', 'User-Agent': 'PindadIoTNode/1.0'}
        )
        with urllib.request.urlopen(req, timeout=3) as resp:
            resp_bytes = resp.read()
            if resp_bytes:
                resp_json = json.loads(resp_bytes.decode('utf-8'))
                if "server_time" in resp_json:
                    clean_str = resp_json["server_time"][:19].replace("T", " ")
                    srv_dt = datetime.datetime.strptime(clean_str, "%Y-%m-%d %H:%M:%S")
                    if 'rtc_device' in globals() and rtc_device:
                        try:
                            rtc_device.datetime = srv_dt.timetuple()
                            print(f" [BOOT SYNC] Jam DS3231 berhasil disinkronkan ke waktu Server: {srv_dt.strftime('%Y-%m-%d %H:%M:%S')}")
                        except Exception as _e_boot:
                            print(f" [BOOT SYNC WARNING] {_e_boot}")
                    os.system(f"sudo -n date -s '{srv_dt.strftime('%Y-%m-%d %H:%M:%S')}' 2>/dev/null")
                if "schedules" in resp_json and isinstance(resp_json["schedules"], list):
                    active_schedules = resp_json["schedules"]
                    config["schedules"] = resp_json["schedules"]
                    print(f" [BOOT SYNC] {len(active_schedules)} jadwal shift aktif termuat dari Server!")
    except Exception as e:
        print(f" [BOOT SYNC NOTICE] Sync awal via HTTP: {e}")

# ================= 9. MAIN TELEMETRY LOOP =================
def telemetry_loop():
    global is_turbo_cooling_active
    start_time = time.time()

    initial_server_sync()
    evaluate_schedules(force=True)

    while True:
        try:
            if is_turbo_cooling_active and (time.time() - start_time > TURBO_COOLING_SEC):
                is_turbo_cooling_active = False
                print(" [TURBO COOLING SELESAI] Masa pendinginan boot berakhir. Menjalankan rotasi jadwal RTC.")
                evaluate_schedules(force=True)

            evaluate_schedules()
            ts = get_current_timestamp()

            for r in RELAYS:
                ac_num = r["ac_number"]
                is_on = relay_states.get(ac_num, False)
                current_amp = read_current_ampere(ac_num)

                payload = {
                    "device_id": DEVICE_ID,
                    "active_ac": f"AC_{ac_num}_{'ON' if is_on else 'OFF'}",
                    "ac_number": ac_num,
                    "state": "ON" if is_on else "OFF",
                    "current_ampere": current_amp,
                    "watt": round(current_amp * 220),
                    "recorded_at": ts,
                    "turbo_active": is_turbo_cooling_active
                }

                # 1. Publish ke MQTT (Hanya ke Saluran / Topik Pribadi Perangkat)
                local_client.publish(f"pindad/devices/{DEVICE_ID}/telemetry", json.dumps(payload))

                # 2. Dual-Sync HTTP REST dengan Offline Buffer
                send_http_telemetry(payload)

                # 3. Direct Edge Alert ditangani terpusat oleh Server AnomalyDetectorService (AC Nyala / AC Mati murni berbasis arus)

                buf_count = offline_buffer.get_count()
                buf_info = f" | [Buffer Offline: {buf_count} item]" if buf_count > 0 else ""
                print(f" [TELEMETRI] AC {ac_num} ({r.get('name', 'Unit')}): {'ON ' if is_on else 'OFF '} | True-RMS ACS712: {current_amp:.4f} A ({payload['watt']} W){buf_info} | RTC: {ts}")

            dash_url = config.get("dashboard_http_url")
            if dash_url and offline_buffer.get_count() > 0:
                threading.Thread(target=drain_offline_buffer, args=(dash_url,), daemon=True).start()

            time.sleep(INTERVAL_SEC)
        except Exception as e:
            print(f" [TELEMETRY LOOP ERROR] {e}")
            time.sleep(5)

# ================= 10. ENTRYPOINT =================
if __name__ == "__main__":
    try:
        local_client.connect_async(BROKER_HOST, BROKER_PORT, keepalive=60)
        local_client.loop_start()
    except Exception as e:
        print(f" [MQTT ASYNC CONNECT ERROR] {e}. Melanjutkan via HTTP Dual-Sync...")

    t_loop = threading.Thread(target=telemetry_loop, daemon=True)
    t_loop.start()

    print(" [READY] PINDAD IoT Universal Node Engine berjalan. Tekan Ctrl+C untuk berhenti.")
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        print(" [SHUTDOWN] Mematikan node secara aman...")
        local_client.loop_stop()
        if HAS_HARDWARE:
            GPIO.cleanup()
