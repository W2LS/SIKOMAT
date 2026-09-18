#!/usr/bin/env python3
"""
=============================================================================
PINDAD IOT ENGINE - INTERACTIVE HARDWARE SETUP WIZARD
PT PINDAD (PERSERO) - SISTEM MONITORING & KONTROL AC
=============================================================================
Wizard interaktif 1-klik untuk mengkonfigurasi nomor GPIO relay, sensor,
dan Device ID tanpa perlu mengedit JSON manual.
=============================================================================
"""

import os
import json
import sys

CONFIG_FILE = os.path.join(os.path.dirname(__file__), "node_config.json")

def print_banner():
    print("\n" + "=" * 65)
    print(" 🚀 PT PINDAD IOT - WIZARD SETUP OTOMATIS RASPBERRY PI ")
    print("=" * 65)
    print(" Wizard ini akan memandu Anda mengatur pin GPIO relay & sensor.")
    print(" Tekan [ENTER] jika ingin menggunakan nilai bawaan (default).")
    print("=" * 65 + "\n")

def ask(prompt, default):
    val = input(f"👉 {prompt} [{default}]: ").strip()
    return val if val else default

def run_wizard():
    print_banner()

    # 1. Identitas Perangkat
    dev_id = ask("Masukkan ID Perangkat (MQTT)", "RPI3B_PINDAD_ROOM_2").upper()
    room_name = ask("Masukkan Nama Ruangan", "Monitoring AC Ruang Server")
    location = ask("Masukkan Lokasi / Gedung", "Gedung TIK")
    broker_ip = ask("Masukkan Alamat IP Server Dashboard", "192.168.197.64")

    # 2. Jumlah Unit AC / Relay
    while True:
        try:
            num_relays_str = ask("Berapa jumlah AC / Relay yang dipasang? (1 - 8)", "2")
            num_relays = int(num_relays_str)
            if 1 <= num_relays <= 8:
                break
            print("⚠️ Harap masukkan angka antara 1 sampai 8.")
        except ValueError:
            print("⚠️ Input tidak valid. Masukkan angka.")

    # Default GPIOs for convenience
    default_gpios = [17, 27, 22, 23, 24, 25, 5, 6]

    relays = []
    print("\n--- 🔌 KONFIGURASI PIN GPIO RELAY ---")
    for i in range(1, num_relays + 1):
        def_gpio = default_gpios[i - 1] if i <= len(default_gpios) else 17 + i
        def_name = f"AC Unit {i}"
        
        while True:
            try:
                gpio_str = ask(f"Nomor Pin GPIO untuk AC {i}", str(def_gpio))
                gpio_pin = int(gpio_str)
                break
            except ValueError:
                print("⚠️ Pin GPIO harus berupa angka (misal: 17, 27, 22).")

        ac_label = ask(f"Label / Nama untuk AC {i}", def_name)
        
        relays.append({
            "ac_number": i,
            "gpio_pin": gpio_pin,
            "name": ac_label,
            "adc_channel": (i - 1) % 4
        })

    # Build Configuration Dict
    config = {
        "device_id": dev_id,
        "room_name": room_name,
        "location": location,
        "mqtt_broker_host": broker_ip,
        "mqtt_broker_port": 1883,
        "sophos_auth": {
            "enabled": True,
            "user": "pin-00020",
            "pass": "5uiFS4eE",
            "url": "https://sophostrn.pindad.com:8090/login.xml"
        },
        "relays": relays
    }

    # Save to node_config.json
    with open(CONFIG_FILE, "w") as f:
        json.dump(config, f, indent=2)

    print("\n" + "=" * 65)
    print(" ✅ KONFIGURASI BERHASIL DISIMPAN KE node_config.json!")
    print(f" 📌 ID Perangkat : {dev_id}")
    print(f" ❄️ Total AC      : {num_relays} Unit")
    for r in relays:
        print(f"    - AC {r['ac_number']}: {r['name']} (GPIO {r['gpio_pin']})")
    print("=" * 65 + "\n")

if __name__ == "__main__":
    run_wizard()
