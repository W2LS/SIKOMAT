@echo off
:: Cek hak akses Administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

title Membuka Port Firewall 1883 dan 8080
cls
echo ================================================================
echo   MEMBUKA AKSES FIREWALL UNTUK KOMUNIKASI RASPBERRY PI
echo ================================================================
echo.
netsh advfirewall firewall add rule name="Mosquitto MQTT Broker 1883" dir=in action=allow protocol=TCP localport=1883
netsh advfirewall firewall add rule name="Mosquitto Program" dir=in action=allow program="C:\Program Files\mosquitto\mosquitto.exe"
netsh advfirewall firewall add rule name="Pindad WebSocket 8080" dir=in action=allow protocol=TCP localport=8080
echo.
echo ================================================================
echo   SUKSES: Port 1883 (MQTT) dan 8080 (WebSocket) Berhasil Dibuka!
echo ================================================================
echo.
pause
