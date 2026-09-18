<?php

namespace App\Services;

use App\Models\AcHourlySummary;
use App\Models\AcLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataRetentionService
{
    /**
     * Pastikan Composite Indexes terpasang di MongoDB untuk performa optimal.
     */
    public function ensureIndexes(): array
    {
        $results = [];
        try {
            $mongoDb = DB::connection('mongodb')->getMongoDB();
            
            // 1. Indexes untuk ac_logs
            $acLogsCol = $mongoDb->selectCollection('ac_logs');
            $acLogsCol->createIndex(['device_id' => 1, 'recorded_at' => -1], ['background' => true]);
            $acLogsCol->createIndex(['ac_number' => 1, 'recorded_at' => -1], ['background' => true]);
            $acLogsCol->createIndex(['recorded_at' => -1], ['background' => true]);
            $results['ac_logs'] = 'Composite indexes (device_id, ac_number, recorded_at) verified.';

            // 2. Indexes untuk ac_hourly_summaries
            $summariesCol = $mongoDb->selectCollection('ac_hourly_summaries');
            $summariesCol->createIndex(['device_id' => 1, 'hour_bucket' => -1], ['background' => true]);
            $summariesCol->createIndex(['hour_bucket' => -1], ['background' => true]);
            $results['ac_hourly_summaries'] = 'Composite indexes (device_id, hour_bucket) verified.';
        } catch (\Throwable $e) {
            Log::warning('DataRetentionService::ensureIndexes: ' . $e->getMessage());
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Agregasi data telemetri mentah berusia > N hari ke format ringkasan per jam,
     * kemudian hapus log mentah yang telah diagregasi untuk membebaskan storage.
     *
     * @param int $retentionDays Jumlah hari data mentah dipertahankan (default: 3)
     * @param string|null $deviceId Filter perangkat tertentu (opsional)
     * @return array Laporan eksekusi agregasi dan pruning
     */
    public function aggregateAndPrune(int $retentionDays = 3, ?string $deviceId = null): array
    {
        $this->ensureIndexes();

        $retentionDays = max(1, $retentionDays);
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        // 1. Ambil seluruh log mentah yang lebih tua dari batas retensi
        $query = AcLog::where('recorded_at', '<', $cutoffDate);
        if ($deviceId && $deviceId !== 'all') {
            $query->where('device_id', $deviceId);
        }

        $rawLogs = $query->orderBy('recorded_at', 'asc')->get();
        $totalRawFound = $rawLogs->count();

        if ($totalRawFound === 0) {
            return [
                'success'           => true,
                'retention_days'    => $retentionDays,
                'cutoff_date'       => $cutoffDate->toDateTimeString(),
                'aggregated_hours'  => 0,
                'pruned_raw_logs'   => 0,
                'retained_raw_logs' => AcLog::count(),
                'message'           => "Tidak ada data telemetri mentah yang lebih tua dari {$retentionDays} hari.",
            ];
        }

        // 2. Kelompokkan data berdasarkan (device_id + ac_number + jam)
        $grouped = [];
        foreach ($rawLogs as $log) {
            $devId = $log->device_id ?? 'UNKNOWN';
            $acNum = (int)($log->ac_number ?? 1);
            
            $recAt = $log->recorded_at ? Carbon::parse($log->recorded_at) : Carbon::now();
            $hourBucketStr = $recAt->copy()->startOfHour()->format('Y-m-d H:00:00');

            $key = "{$devId}|{$acNum}|{$hourBucketStr}";
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'device_id'   => $devId,
                    'ac_number'   => $acNum,
                    'hour_bucket' => Carbon::parse($hourBucketStr),
                    'samples'     => [],
                ];
            }

            $current = (float)($log->current_ampere ?? 0.0);
            $grouped[$key]['samples'][] = $current;
        }

        // 3. Simpan / Upsert ringkasan per jam ke koleksi ac_hourly_summaries
        $aggregatedCount = 0;
        foreach ($grouped as $g) {
            $samples = $g['samples'];
            $sampleCount = count($samples);
            if ($sampleCount === 0) continue;

            $avgCurrent = round(array_sum($samples) / $sampleCount, 2);
            $minCurrent = round(min($samples), 2);
            $maxCurrent = round(max($samples), 2);

            $avgWatt = round($avgCurrent * 220, 1);
            $minWatt = round($minCurrent * 220, 1);
            $maxWatt = round($maxCurrent * 220, 1);
            $estKwh = round(($avgWatt * 1.0) / 1000, 4);

            $onCount = count(array_filter($samples, fn($val) => $val >= 0.12));
            $offCount = $sampleCount - $onCount;
            $uptimePct = round(($onCount / $sampleCount) * 100, 1);

            AcHourlySummary::updateOrCreate(
                [
                    'device_id'   => $g['device_id'],
                    'ac_number'   => $g['ac_number'],
                    'hour_bucket' => $g['hour_bucket'],
                ],
                [
                    'avg_current'       => $avgCurrent,
                    'min_current'       => $minCurrent,
                    'max_current'       => $maxCurrent,
                    'avg_watt'          => $avgWatt,
                    'min_watt'          => $minWatt,
                    'max_watt'          => $maxWatt,
                    'est_kwh'           => $estKwh,
                    'sample_count'      => $sampleCount,
                    'on_sample_count'   => $onCount,
                    'off_sample_count'  => $offCount,
                    'uptime_percentage' => $uptimePct,
                ]
            );

            $aggregatedCount++;
        }

        // 4. Hapus log mentah lama yang telah selesai diringkas
        $deleteQuery = AcLog::where('recorded_at', '<', $cutoffDate);
        if ($deviceId && $deviceId !== 'all') {
            $deleteQuery->where('device_id', $deviceId);
        }
        $prunedCount = $deleteQuery->delete();

        return [
            'success'           => true,
            'retention_days'    => $retentionDays,
            'cutoff_date'       => $cutoffDate->toDateTimeString(),
            'aggregated_hours'  => $aggregatedCount,
            'pruned_raw_logs'   => $prunedCount,
            'retained_raw_logs' => AcLog::count(),
            'message'           => "Berhasil meringkas {$aggregatedCount} jam histori dan membersihkan {$prunedCount} baris log mentah (Retensi: {$retentionDays} hari).",
        ];
    }

    /**
     * Ambil statistik penyimpanan log telemetri di MongoDB.
     */
    public function getStorageStats(): array
    {
        $totalRaw = AcLog::count();
        $totalSummaries = AcHourlySummary::count();

        $oldest = AcLog::orderBy('recorded_at', 'asc')->first();
        $newest = AcLog::orderBy('recorded_at', 'desc')->first();

        // Estimasi ukuran penyimpanan (~250 bytes per row raw vs ~150 bytes per hourly summary)
        $rawSizeKb = round(($totalRaw * 250) / 1024, 1);
        $summarySizeKb = round(($totalSummaries * 150) / 1024, 1);

        return [
            'total_raw_logs'          => $totalRaw,
            'total_hourly_summaries'  => $totalSummaries,
            'oldest_log_date'         => $oldest && $oldest->recorded_at ? Carbon::parse($oldest->recorded_at)->format('d M Y H:i') : '-',
            'newest_log_date'         => $newest && $newest->recorded_at ? Carbon::parse($newest->recorded_at)->format('d M Y H:i') : '-',
            'raw_storage_est_kb'      => $rawSizeKb,
            'summary_storage_est_kb'  => $summarySizeKb,
            'total_storage_est_mb'    => round(($rawSizeKb + $summarySizeKb) / 1024, 2),
        ];
    }
}
