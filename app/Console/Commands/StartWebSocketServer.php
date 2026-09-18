<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StartWebSocketServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'websocket:serve {--port=8080 : The port to listen on}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start high-performance SIKOMAT AC Realtime WebSocket Server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $port = (int)$this->option('port') ?: 8080;
        $script = base_path('scripts/pindad_websocket_server.py');

        $this->info("Starting SIKOMAT AC High-Performance WebSocket Server on port {$port}...");
        $this->line("WebSocket Endpoint: ws://127.0.0.1:{$port}");

        passthru("python \"{$script}\"");

        return self::SUCCESS;
    }
}
