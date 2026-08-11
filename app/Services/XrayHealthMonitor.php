<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class XrayHealthMonitor
{
    public function __construct(
        private XrayStatsReader $stats,
        private XrayServiceRestarter $restarter,
        private JoyCredentialClient $joy,
        private XrayDesiredStateReconciler $desiredState,
    ) {}

    public function check(): array
    {
        return Cache::lock('xray-health-check', 25)->block(2, function (): array {
            $result = ['healthy' => false, 'restarted' => false, 'error' => null];
            try {
                $this->stats->read();
                $result['healthy'] = true;
            } catch (Throwable $initial) {
                $result['error'] = $initial->getMessage();
                if (Cache::add('xray-health-restart-cooldown', true, (int) config('xray.restart_cooldown', 60))) {
                    try {
                        $this->restarter->restart();
                        $result['restarted'] = true;
                        $deadline = microtime(true) + (int) config('xray.restart_wait', 20);
                        do {
                            try {
                                $this->stats->read();
                                $result['healthy'] = true;
                                $result['error'] = null;
                                break;
                            } catch (Throwable $retry) {
                                $result['error'] = $retry->getMessage();
                                usleep(750000);
                            }
                        } while (microtime(true) < $deadline);

                        if ($result['healthy'] && config('xray.user_writes_enabled') && config('xray.user_reconcile_enabled')) {
                            $this->desiredState->reconcile();
                        }
                    } catch (Throwable $restartError) {
                        $result['error'] = $restartError->getMessage();
                    }
                }
            }

            try {
                $this->joy->reportHealth($result);
            } catch (Throwable $reportError) {
                Log::warning('Could not report node health to Joy.', ['message' => $reportError->getMessage()]);
            }
            return $result;
        });
    }
}
