<?php

namespace App\Console\Commands;

use App\Services\XrayHealthMonitor;
use Illuminate\Console\Command;
use Throwable;

final class MonitorXrayHealth extends Command
{
    protected $signature = 'xray:health:watch {--once} {--interval=}';
    protected $description = 'Continuously monitor Xray and restart it when its API becomes unavailable';

    public function handle(XrayHealthMonitor $monitor): int
    {
        if (!config('xray.health_enabled', true)) {
            $this->warn('Xray health monitoring is disabled.');
            return self::SUCCESS;
        }
        $interval = max(10, (int) ($this->option('interval') ?: config('xray.health_interval', 30)));
        do {
            try {
                $result = $monitor->check();
                $message = $result['healthy'] ? 'healthy' : 'unhealthy';
                if ($result['restarted']) $message .= ', restarted';
                $this->line(now()->toDateTimeString().' '.$message.($result['error'] ? ': '.$result['error'] : ''));
            } catch (Throwable $e) {
                report($e);
                $this->error(now()->toDateTimeString().' monitor error: '.$e->getMessage());
            }
            if ($this->option('once')) break;
            sleep($interval);
        } while (true);
        return self::SUCCESS;
    }
}
