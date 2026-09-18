<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use App\Services\MqttService;

#[Signature('mqtt:publish {topic} {message}')]
#[Description('Publish a message to an EMQX MQTT topic')]
class MqttPublisher extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MqttService $mqttService)
    {
        $topic = $this->argument('topic');
        $message = $this->argument('message');

        $this->info("Publishing to topic [{$topic}]: {$message}");

        $success = $mqttService->publish($topic, $message);

        if ($success) {
            $this->info("Message published successfully!");
        } else {
            $this->error("Failed to publish message.");
        }
    }
}
