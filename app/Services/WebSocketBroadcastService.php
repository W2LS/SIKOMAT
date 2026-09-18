<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WebSocketBroadcastService
{
    /**
     * Broadcast an event payload to the local WebSocket server on port 8080.
     */
    public function broadcast(string $eventType, array $payload = []): bool
    {
        try {
            $data = [
                'type' => $eventType,
                'data' => $payload,
                'timestamp' => microtime(true),
            ];

            $ch = curl_init('http://127.0.0.1:8080/broadcast');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 300); // 300ms non-blocking
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $res = curl_exec($ch);
            curl_close($ch);
            return true;
        } catch (\Throwable $e) {
            Log::debug('WebSocketBroadcastService::broadcast notice: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Broadcast device-specific telemetry event to connected clients.
     */
    public function broadcastDeviceTelemetry(string $deviceId, array $payload = []): bool
    {
        return $this->broadcast('telemetry_updated', array_merge([
            'device_id' => $deviceId,
        ], $payload));
    }
}
