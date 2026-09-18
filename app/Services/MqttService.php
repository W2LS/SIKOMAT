<?php

namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use Illuminate\Support\Facades\Log;

class MqttService
{
    protected string $server;
    protected int $port;
    protected string $clientId;
    protected ?string $username;
    protected ?string $password;

    public function __construct()
    {
        $this->server = env('MQTT_HOST', '127.0.0.1');
        $this->port = (int) env('MQTT_PORT', 1883);
        $this->clientId = env('MQTT_CLIENT_ID', 'laravel_mqtt_publisher') . '_' . rand(1000, 9999);
        
        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');
        $this->username = $username === 'null' ? null : $username;
        $this->password = $password === 'null' ? null : $password;
    }

    /**
     * Publish a message to a topic.
     *
     * @param string $topic
     * @param string $message
     * @param int $qos
     * @param bool $retain
     * @return bool
     */
    public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): bool
    {
        try {
            $mqtt = new MqttClient($this->server, $this->port, $this->clientId);

            $settings = (new ConnectionSettings)
                ->setUsername($this->username)
                ->setPassword($this->password)
                ->setKeepAliveInterval(10);

            $mqtt->connect($settings, true);
            $mqtt->publish($topic, $message, $qos, $retain);
            $mqtt->disconnect();

            return true;
        } catch (MqttClientException $e) {
            Log::error("MQTT Publish failed: " . $e->getMessage());
            return false;
        }
    }
}
