<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;

class PruneTelemetryLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:prune 
                            {--days=3 : Number of days to retain raw telemetry} 
                            {--device= : Specific device ID to prune} 
                            {--index : Ensure MongoDB indexes only without pruning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform hourly aggregation on old telemetry logs and purge raw data to free storage';

    /**
     * Execute the console command.
     */
    public function handle(DataRetentionService $retentionService): int
    {
        if ($this->option('index')) {
            $this->info('Verifying MongoDB composite indexes for SIKOMAT AC...');
            $res = $retentionService->ensureIndexes();
            $this->table(['Collection', 'Status'], array_map(fn($k, $v) => [$k, $v], array_keys($res), array_values($res)));
            return self::SUCCESS;
        }

        $days = (int)$this->option('days') ?: 3;
        $device = $this->option('device');

        $this->info("Starting SIKOMAT AC Telemetry Data Retention & Auto-Pruning Engine...");
        $this->line(" - Retention Window : {$days} days");
        $this->line(" - Target Device   : " . ($device ?: 'All Devices'));

        $result = $retentionService->aggregateAndPrune($days, $device);

        if (!empty($result['success'])) {
            $this->newLine();
            $this->info("✔ Execution Completed Successfully:");
            $this->line(" - Aggregated Hourly Summaries : " . $result['aggregated_hours']);
            $this->line(" - Pruned Raw Log Rows         : " . $result['pruned_raw_logs']);
            $this->line(" - Remaining Raw Telemetry     : " . $result['retained_raw_logs']);
            $this->line(" - Cutoff Date                 : " . $result['cutoff_date']);
            return self::SUCCESS;
        }

        $this->error("Failed to execute data pruning.");
        return self::FAILURE;
    }
}
