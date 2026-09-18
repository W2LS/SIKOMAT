<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Schedule;
use App\Models\AcLog;
use App\Models\Template;
use App\Models\Device;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database in MongoDB.
     */
    public function run(): void
    {
        // 1. Seed Administrator User
        User::updateOrCreate(
            ['email' => 'dicky.akbar@pindad.com'],
            [
                'name' => 'Dicky Akbar Syah Putra',
                'password' => Hash::make('pindad123'),
            ]
        );

        // 2. Seed IoT Device Templates Standar (PINDAD Standard Datastreams)
        Template::truncate();

        Template::create([
            'name' => 'Dual AC Relay Controller',
            'hardware_type' => 'Raspberry Pi 3B+',
            'connection_type' => 'MQTT Broker (TCP 1883)',
            'icon' => '❄️',
            'description' => 'Blueprint kontrol 2 relay AC dengan sensor arus ACS712 & RTC DS3231.',
            'datastreams' => [
                ['pin' => 'V0', 'name' => 'Relay AC 1 (Panasonic 1)', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'unit' => '', 'desc' => 'Saklar manual/otomatis unit AC 1'],
                ['pin' => 'V1', 'name' => 'Relay AC 2 (Panasonic 2)', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'unit' => '', 'desc' => 'Saklar manual/otomatis unit AC 2'],
                ['pin' => 'V2', 'name' => 'Arus Listrik AC 1', 'type' => 'Double', 'min' => 0, 'max' => 30, 'unit' => 'A', 'desc' => 'Sensor arus ACS712 AC 1'],
                ['pin' => 'V3', 'name' => 'Arus Listrik AC 2', 'type' => 'Double', 'min' => 0, 'max' => 30, 'unit' => 'A', 'desc' => 'Sensor arus ACS712 AC 2'],
                ['pin' => 'V4', 'name' => 'Total Konsumsi Daya', 'type' => 'Integer', 'min' => 0, 'max' => 10000, 'unit' => 'W', 'desc' => 'Akumulasi beban daya terukur'],
                ['pin' => 'V5', 'name' => 'Turbo Cooling Priority', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'unit' => '', 'desc' => 'Status proteksi prioritas pendinginan'],
            ]
        ]);

        Template::create([
            'name' => 'Smart Industrial Lighting',
            'hardware_type' => 'ESP32 Dual-Core IoT',
            'connection_type' => 'WiFi (IEEE 802.11 b/g/n)',
            'icon' => '💡',
            'description' => 'Blueprint saklar otomatis lampu koridor gedung & sensor intensitas cahaya LDR.',
            'datastreams' => [
                ['pin' => 'V0', 'name' => 'Saklar Lampu Utama', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'unit' => '', 'desc' => 'Kontrol relay lampu selasar'],
                ['pin' => 'V1', 'name' => 'Intensitas Cahaya Ambient', 'type' => 'Integer', 'min' => 0, 'max' => 1024, 'unit' => 'Lux', 'desc' => 'Sensor LDR koridor'],
                ['pin' => 'V2', 'name' => 'Konsumsi Daya Lampu', 'type' => 'Integer', 'min' => 0, 'max' => 500, 'unit' => 'W', 'desc' => 'Daya lampu LED terukur'],
            ]
        ]);

        Template::create([
            'name' => 'Data Center Precision Cooler',
            'hardware_type' => 'Raspberry Pi 4 Model B',
            'connection_type' => 'Gigabit Ethernet LAN',
            'icon' => '🏢',
            'description' => 'Blueprint pendingin presisi ruang server rack data center pusat PT PINDAD.',
            'datastreams' => [
                ['pin' => 'V0', 'name' => 'Cooling Unit 1 & 2', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'unit' => '', 'desc' => 'Compressor 1 & 2'],
                ['pin' => 'V1', 'name' => 'Cooling Unit 3 & 4', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'unit' => '', 'desc' => 'Compressor 3 & 4'],
                ['pin' => 'V2', 'name' => 'Total Beban Rack DC', 'type' => 'Integer', 'min' => 0, 'max' => 20000, 'unit' => 'W', 'desc' => 'Daya pendingin data center'],
            ]
        ]);
    }
}
