<?php

namespace App\Console\Commands;

use App\Services\XrayStatsReader;
use Illuminate\Console\Command;
use Throwable;

class TestXrayStats extends Command
{
    protected $signature = 'xray:stats:test {--json : Print machine-readable JSON}';

    protected $description = 'Test Xray StatsService and list per-user counters without writing to the database';

    public function handle(XrayStatsReader $reader): int
    {
        try {
            $users = $reader->read();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($users === []) {
            $this->warn('StatsService is reachable, but no per-user counters were returned.');
            $this->line('Check client email values and statsUserUplink/statsUserDownlink policy settings.');

            return self::SUCCESS;
        }

        $this->table(
            ['email', 'uplink bytes', 'downlink bytes', 'total bytes'],
            collect($users)->map(fn (array $counter, string $email) => [
                $email,
                $counter['uplink'],
                $counter['downlink'],
                $counter['uplink'] + $counter['downlink'],
            ])->values()->all()
        );

        $this->info(count($users).' user counter(s) found. No database changes were made.');

        return self::SUCCESS;
    }
}
