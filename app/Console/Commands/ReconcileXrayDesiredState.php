<?php

namespace App\Console\Commands;

use App\Services\XrayDesiredStateReconciler;
use Illuminate\Console\Command;
use Throwable;

final class ReconcileXrayDesiredState extends Command
{
    protected $signature = 'xray:desired:reconcile';
    protected $description = 'Restore persistent inbounds first and runtime users second';

    public function handle(XrayDesiredStateReconciler $reconciler): int
    {
        try {
            $result = $reconciler->reconcile();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
        $failed = $result['inboundEnsure']['failed'] + $result['users']['failed'] +
            $result['inboundCleanup']['failed'];
        $this->line(sprintf('Inbounds added %d; users added %d; users removed %d; failed %d.',
            $result['inboundEnsure']['added'], $result['users']['added'],
            $result['users']['removed'], $failed));
        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
