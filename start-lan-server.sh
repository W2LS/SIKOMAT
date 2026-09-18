#!/usr/bin/env bash

# ===============================================================================
# SIKOMAT AC - Server Laravel LAN Launcher (Linux / macOS / Raspberry Pi)
# PT PINDAD (PERSERO) - DIVISI MUTU & TI
# ===============================================================================

# Pindah ke direktori tempat script ini berada
cd "$(dirname "$0")" || exit 1

# Warna terminal ANSI
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m' # No Color

clear
echo -e "${GREEN}===============================================================================${NC}"
echo -e "${BOLD}${GREEN}    SISTEM KONTROL DAN MONITORING SUHU AC OTOMATIS (SIKOMAT)${NC}"
echo -e "${GREEN}    PT PINDAD (PERSERO) - DIVISI MUTU & TI${NC}"
echo -e "${GREEN}===============================================================================${NC}"
echo ""

# 1. Cek ketersediaan PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}[ERROR] PHP tidak terdeteksi di sistem ini!${NC}"
    echo "Silakan install PHP terlebih dahulu (contoh di Ubuntu/Raspberry Pi: sudo apt install php php-cli php-sqlite3 php-curl php-mbstring php-xml)"
    exit 1
fi

# 2. Pastikan file database SQLite ada
if [ ! -f "database/database.sqlite" ] && [ -d "database" ]; then
    touch "database/database.sqlite"
fi

# 3. Pastikan file .env ada
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    echo -e "${CYAN}[*] Menyiapkan file konfigurasi .env dari .env.example...${NC}"
    cp ".env.example" ".env"
    php artisan key:generate
fi

echo -e "${CYAN}[1/3] Mendeteksi Alamat IP Komputer pada Jaringan Lokal (LAN / Wi-Fi)...${NC}"
echo "-------------------------------------------------------------------------------"

# Deteksi IP di Linux / Raspberry Pi / macOS
IP_LIST=()
PRIMARY_IP=""

if command -v hostname &> /dev/null && hostname -I &> /dev/null; then
    # Linux / Raspberry Pi
    for ip in $(hostname -I); do
        if [[ "$ip" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] && [[ "$ip" != 127.* ]]; then
            IP_LIST+=("$ip")
        fi
    done
elif command -v ip &> /dev/null; then
    # Linux fallback
    for ip in $(ip -4 addr show scope global 2>/dev/null | awk '$1 ~ /^inet/ {print $2}' | cut -d/ -f1); do
        if [[ "$ip" != 127.* ]]; then
            IP_LIST+=("$ip")
        fi
    done
elif command -v ifconfig &> /dev/null; then
    # macOS / BSD
    for ip in $(ifconfig 2>/dev/null | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}'); do
        IP_LIST+=("$ip")
    done
fi

# Cari IP utama (prioritaskan 192.168.x.x atau 10.x.x.x)
for ip in "${IP_LIST[@]}"; do
    if [[ "$ip" =~ ^192\.168\. ]] || [[ "$ip" =~ ^10\. ]]; then
        PRIMARY_IP="$ip"
        break
    fi
done

# Jika tidak ada 192.168 / 10, ambil IP non-loopback pertama
if [ -z "$PRIMARY_IP" ] && [ ${#IP_LIST[@]} -gt 0 ]; then
    PRIMARY_IP="${IP_LIST[0]}"
fi

# Fallback ke 127.0.0.1 jika tidak ada IP
if [ -z "$PRIMARY_IP" ]; then
    PRIMARY_IP="127.0.0.1"
fi

# Tampilkan daftar IP
for ip in "${IP_LIST[@]}"; do
    if [ "$ip" == "$PRIMARY_IP" ]; then
        echo -e "  ${GREEN}[+] IP LAN Terdeteksi : $ip (Utama / Wi-Fi / LAN)${NC}"
    else
        echo -e "  [+] IP LAN Terdeteksi : $ip"
    fi
done

if [ ${#IP_LIST[@]} -eq 0 ]; then
    echo -e "  [!] Tidak ada koneksi LAN/Wi-Fi aktif, menggunakan Localhost."
fi

echo "-------------------------------------------------------------------------------"
echo ""
echo -e "${CYAN}[2/3] Alamat Akses Web Dashboard SIKOMAT:${NC}"
echo -e "  - Komputer Ini (Localhost) : ${BOLD}http://127.0.0.1:8000${NC}"
echo -e "  - Komputer / Laptop Lain   : ${BOLD}${GREEN}http://${PRIMARY_IP}:8000${NC}"
echo -e "  - Node Raspberry Pi        : ${BOLD}${GREEN}http://${PRIMARY_IP}:8000${NC}"
echo ""
echo -e "${CYAN}[3/3] Menjalankan Server Laravel (Host: 0.0.0.0, Port: 8000)...${NC}"
echo "Tekan CTRL + C untuk menghentikan server."
echo -e "${GREEN}===============================================================================${NC}"
echo ""

php artisan serve --host=0.0.0.0 --port=8000
