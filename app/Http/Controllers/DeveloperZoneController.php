<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeveloperZoneController extends Controller
{
    public function storeTemplate(Request $request)
    {
        $userNip = session('user_nip', 'PINDAD-IOT-2026');

        $request->validate([
            'name' => 'required|string|max:100',
            'hardware_type' => 'required|string|max:100',
            'connection_type' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        $tmpl = Template::create([
            'user_nip' => $userNip,
            'name' => $request->input('name'),
            'hardware_type' => $request->input('hardware_type'),
            'connection_type' => $request->input('connection_type'),
            'icon' => $request->input('icon', '⚡'),
            'description' => $request->input('description', ''),
            'datastreams' => [], // Kosongan secara default agar user bebas menambahkan Datastream sendiri
        ]);

        return redirect()->back()
            ->with('success', "Template {$request->input('name')} berhasil dibuat (kosongan)! Silakan tambahkan Datastream sesuai kebutuhan.")
            ->with('selected_template_id', (string)$tmpl->id);
    }

    /**
     * Developer Zone: Update Template.
     */

    public function updateTemplate(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'hardware_type' => 'required|string|max:100',
            'connection_type' => 'required|string|max:100',
            'icon' => 'nullable|string|max:10',
            'description' => 'nullable|string',
        ]);

        $template = Template::findOrFail($id);
        $template->update($request->only('name', 'hardware_type', 'connection_type', 'icon', 'description'));

        return redirect()->back()
            ->with('success', "Template {$template->name} berhasil diperbarui!")
            ->with('selected_template_id', (string)$template->id);
    }

    /**
     * Developer Zone: Delete Template.
     */

    public function deleteTemplate(string $id)
    {
        $template = Template::findOrFail($id);
        $name = $template->name;
        $template->delete();

        return redirect()->back()->with('success', "Template {$name} berhasil dihapus.");
    }

    /**
     * Developer Zone: Add Datastream to Template.
     */

    public function addDatastream(Request $request, string $id)
    {
        $request->validate([
            'pin' => 'required|string|max:10',
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:Integer,Double,String,Enum',
            'min' => 'nullable|numeric',
            'max' => 'nullable|numeric',
            'default_value' => 'nullable|string|max:50',
            'unit' => 'nullable|string|max:20',
            'desc' => 'nullable|string|max:200',
        ]);

        $template = Template::findOrFail($id);
        $streams = $template->datastreams ?? [];

        // Check if pin exists
        foreach ($streams as $s) {
            if ($s['pin'] === strtoupper($request->input('pin'))) {
                return redirect()->back()
                    ->with('error', "Pin {$request->input('pin')} sudah terdaftar pada template ini!")
                    ->with('selected_template_id', (string)$template->id);
            }
        }

        $streams[] = [
            'pin'           => strtoupper($request->input('pin')),
            'name'          => $request->input('name'),
            'type'          => $request->input('type'),
            'min'           => $request->input('min', 0),
            'max'           => $request->input('max', 100),
            'default_value' => $request->input('default_value', '0'),
            'unit'          => $request->input('unit', ''),
            'desc'          => $request->input('desc', ''),
        ];

        $template->datastreams = $streams;
        $template->save();

        return redirect()->back()
            ->with('success', "Datastream {$request->input('pin')} ({$request->input('name')}) berhasil ditambahkan ke template {$template->name}!")
            ->with('selected_template_id', (string)$template->id);
    }

    /**
     * Developer Zone: Delete Datastream from Template.
     */

    public function deleteDatastream(string $id, string $pin)
    {
        $template = Template::findOrFail($id);
        $streams = collect($template->datastreams ?? [])->reject(function ($s) use ($pin) {
            return $s['pin'] === $pin;
        })->values()->all();

        $template->datastreams = $streams;
        $template->save();

        return redirect()->back()
            ->with('success', "Datastream {$pin} berhasil dihapus dari template {$template->name}.")
            ->with('selected_template_id', (string)$template->id);
    }

    /**
     * Developer Zone: Export Template as JSON file.
     */

    public function exportTemplate(string $id)
    {
        $template = Template::findOrFail($id);

        $exportData = [
            'pindad_iot_version' => '1.0',
            'exported_at'        => now('Asia/Jakarta')->toDateTimeString(),
            'name'               => $template->name,
            'hardware_type'      => $template->hardware_type,
            'connection_type'    => $template->connection_type,
            'icon'               => $template->icon ?? '⚡',
            'description'        => $template->description ?? '',
            'datastreams'        => $template->datastreams ?? [],
        ];

        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fileName = 'pindad_template_' . Str::slug($template->name, '_') . '.json';

        return response($json, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Developer Zone: Import Template from JSON file or JSON string.
     */

    public function importTemplate(Request $request)
    {
        $jsonContent = null;

        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $jsonContent = file_get_contents($file->getRealPath());
        } elseif ($request->filled('template_json')) {
            $jsonContent = $request->input('template_json');
        }

        if (!$jsonContent) {
            return redirect()->back()->with('error', 'Silakan unggah file JSON atau tempel teks JSON template.');
        }

        try {
            $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Format file JSON tidak valid: ' . $e->getMessage());
        }

        if (empty($data['name'])) {
            return redirect()->back()->with('error', 'File JSON tidak memiliki nama template yang valid.');
        }

        // Clean & validate datastreams
        $datastreams = [];
        if (!empty($data['datastreams']) && is_array($data['datastreams'])) {
            foreach ($data['datastreams'] as $ds) {
                if (!empty($ds['pin']) && !empty($ds['name'])) {
                    $datastreams[] = [
                        'pin'           => strtoupper(trim($ds['pin'])),
                        'name'          => trim($ds['name']),
                        'type'          => $ds['type'] ?? 'Integer',
                        'min'           => $ds['min'] ?? 0,
                        'max'           => $ds['max'] ?? 100,
                        'default_value' => (string)($ds['default_value'] ?? '0'),
                        'unit'          => $ds['unit'] ?? '',
                        'desc'          => $ds['desc'] ?? '',
                    ];
                }
            }
        }

        // Create new imported template
        $template = Template::create([
            'name'            => $data['name'],
            'hardware_type'   => $data['hardware_type'] ?? 'Raspberry Pi 3B+',
            'connection_type' => $data['connection_type'] ?? 'WiFi / Ethernet (MQTT)',
            'icon'            => $data['icon'] ?? '⚡',
            'description'     => $data['description'] ?? 'Blueprint diimpor dari file JSON',
            'datastreams'     => $datastreams,
        ]);

        return redirect()->back()
            ->with('success', "Template {$template->name} berhasil diimpor dengan " . count($datastreams) . " Datastream!")
            ->with('selected_template_id', (string)$template->id);
    }

    /**
     * Developer Zone: Create Standard Preset Template (1, 2, 4, 8 Channel Relay).
     */

    public function createPresetTemplate(Request $request)
    {
        $preset = $request->input('preset_type', 'relay_2ch');

        $presetsConfig = [
            'relay_1ch' => [
                'name'            => 'Module Relay 1 Channel',
                'hardware_type'   => 'Raspberry Pi 3B+',
                'connection_type' => 'WiFi / Ethernet (MQTT)',
                'icon'            => '⚡',
                'description'     => 'Blueprint kontrol 1 unit AC / Beban Tunggal dengan pembacaan sensor arus ACS712.',
                'datastreams'     => [
                    ['pin' => 'V0', 'name' => 'AC 1', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V1', 'name' => 'Arus AC1', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V2', 'name' => 'Total Arus', 'type' => 'Double', 'min' => 0, 'max' => 5000, 'default_value' => '0', 'unit' => 'W', 'desc' => ''],
                    ['pin' => 'V3', 'name' => 'Turbo Cooling Priority', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                ],
            ],
            'relay_2ch' => [
                'name'            => 'Module Relay 2 Channel',
                'hardware_type'   => 'Raspberry Pi 3B+',
                'connection_type' => 'WiFi / Ethernet (MQTT)',
                'icon'            => '⚡',
                'description'     => 'Blueprint standar dual AC Ruang Server dengan rotasi shift 12 jam DS3231 dan fail-safe recovery.',
                'datastreams'     => [
                    ['pin' => 'V0', 'name' => 'AC 1', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V1', 'name' => 'AC 2', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V2', 'name' => 'Arus AC1', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V3', 'name' => 'Arus AC2', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V4', 'name' => 'Total Arus', 'type' => 'Double', 'min' => 0, 'max' => 10000, 'default_value' => '0', 'unit' => 'W', 'desc' => ''],
                    ['pin' => 'V5', 'name' => 'Turbo Cooling Priority', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                ],
            ],
            'relay_4ch' => [
                'name'            => 'Module Relay 4 Channel',
                'hardware_type'   => 'Raspberry Pi 3B+',
                'connection_type' => 'WiFi / Ethernet (MQTT)',
                'icon'            => '⚡',
                'description'     => 'Blueprint industri 4 unit AC / Ruang Data Center dengan monitoring beban 4 kanal.',
                'datastreams'     => [
                    ['pin' => 'V0', 'name' => 'AC 1', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V1', 'name' => 'AC 2', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V2', 'name' => 'AC 3', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V3', 'name' => 'AC 4', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V4', 'name' => 'Arus AC1', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V5', 'name' => 'Arus AC2', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V6', 'name' => 'Arus AC3', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V7', 'name' => 'Arus AC4', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V8', 'name' => 'Total Arus', 'type' => 'Double', 'min' => 0, 'max' => 20000, 'default_value' => '0', 'unit' => 'W', 'desc' => ''],
                    ['pin' => 'V9', 'name' => 'Turbo Cooling Priority', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                ],
            ],
            'relay_8ch' => [
                'name'            => 'Module Relay 8 Channel',
                'hardware_type'   => 'Raspberry Pi 3B+',
                'connection_type' => 'WiFi / Ethernet (MQTT)',
                'icon'            => '⚡',
                'description'     => 'Blueprint kapasitas penuh 8 unit pendingin pabrik / chiller dengan telemetri multi-ADC.',
                'datastreams'     => [
                    ['pin' => 'V0', 'name' => 'AC 1', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V1', 'name' => 'AC 2', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V2', 'name' => 'AC 3', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V3', 'name' => 'AC 4', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V4', 'name' => 'AC 5', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V5', 'name' => 'AC 6', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V6', 'name' => 'AC 7', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V7', 'name' => 'AC 8', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V8', 'name' => 'Arus AC1', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V9', 'name' => 'Arus AC2', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V10', 'name' => 'Arus AC3', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V11', 'name' => 'Arus AC4', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V12', 'name' => 'Arus AC5', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V13', 'name' => 'Arus AC6', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V14', 'name' => 'Arus AC7', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V15', 'name' => 'Arus AC8', 'type' => 'Double', 'min' => 0, 'max' => 30, 'default_value' => '0', 'unit' => 'A', 'desc' => ''],
                    ['pin' => 'V16', 'name' => 'Total Arus', 'type' => 'Double', 'min' => 0, 'max' => 50000, 'default_value' => '0', 'unit' => 'W', 'desc' => ''],
                    ['pin' => 'V17', 'name' => 'Turbo Cooling Priority', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                ],
            ],
            'corridor_lighting' => [
                'name'            => 'Smart Corridor Lighting (ESP32 / RPi)',
                'hardware_type'   => 'Raspberry Pi 3B+ / ESP32',
                'connection_type' => 'WiFi (MQTT)',
                'icon'            => '💡',
                'description'     => 'Blueprint otomasi 4 zona pencahayaan lorong & gedung PT PINDAD.',
                'datastreams'     => [
                    ['pin' => 'V0', 'name' => 'Lampu Zona A (Utara)', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V1', 'name' => 'Lampu Zona B (Selatan)', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V2', 'name' => 'Lampu Zona C (Timur)', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                    ['pin' => 'V3', 'name' => 'Lampu Zona D (Barat)', 'type' => 'Integer', 'min' => 0, 'max' => 1, 'default_value' => '0', 'unit' => '', 'desc' => ''],
                ],
            ],
        ];

        $cfg = $presetsConfig[$preset] ?? $presetsConfig['relay_2ch'];

        $template = Template::create([
            'name'            => $cfg['name'],
            'hardware_type'   => $cfg['hardware_type'],
            'connection_type' => $cfg['connection_type'],
            'icon'            => $cfg['icon'],
            'description'     => $cfg['description'],
            'datastreams'     => $cfg['datastreams'],
        ]);

        return redirect()->back()
            ->with('success', "Preset {$template->name} berhasil dibuat dengan " . count($cfg['datastreams']) . " Datastreams!")
            ->with('selected_template_id', (string)$template->id);
    }

    /**
     * Update Profile Operator.
     */

}
