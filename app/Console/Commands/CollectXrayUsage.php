<?php

namespace App\Console\Commands;

use App\Services\XrayUsageCollector;
use Illuminate\Console\Command;
use Throwable;

class CollectXrayUsage extends Command
{
    protected $signature = 'xray:usage:collect
        {--json : Print machine-readable JSON}
        {--include-zero : Include users whose delta is zero}';

    protected $description = 'Read and persist per-user Xray counters without modifying user quota';

    public function handle(XrayUsageCollector $collector): int
    {
        try {
            $result = $collector->collect();
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $users = collect($result['users']);

        if (!$this->option('include-zero')) {
            $users = $users->filter(fn (array $row) =>
                $row['first_observation'] || $row['total_delta_bytes'] > 0
            );
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'collection_id' => $result['collection_id'],
                'users' => $users->values()->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Collection: '.$result['collection_id']);

        if ($users->isEmpty()) {
            $this->line('No changed user counters were returned by Xray.');

            return self::SUCCESS;
        }

        $this->table(
            ['email', 'up total', 'down total', 'delta', 'state'],
            $users->map(function (array $row) {
                return [
                    $row['email'],
                    $this->formatBytes($row['uplink_total_bytes']),
                    $this->formatBytes($row['downlink_total_bytes']),
                    $this->formatBytes($row['total_delta_bytes']),
                    $row['first_observation']
                        ? 'baseline'
                        : ($row['counter_reset_detected'] ? 'counter reset' : 'ok'),
                ];
            })->all()
        );

        if ($result['first_observation']) {
            $this->warn('Baseline created. First observation is intentionally not counted as delta.');
        }

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes;

        foreach ($units as $unit) {
            $value /= 1024;
            if ($value < 1024 || $unit === 'TB') {
                return number_format($value, 2).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
