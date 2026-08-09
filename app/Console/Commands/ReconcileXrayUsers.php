<?php

namespace App\Console\Commands;

use App\Services\XrayRuntimeUserReconciler;
use Illuminate\Console\Command;

final class ReconcileXrayUsers extends Command
{
    protected $signature = 'xray:users:reconcile {--email= : Reconcile only one exact email}';
    protected $description = 'Restore persistent runtime users missing after an Xray restart';

    public function handle(XrayRuntimeUserReconciler $reconciler): int
    {
        $result = $reconciler->reconcile($this->option('email') ?: null);

        $this->line(sprintf(
            'Checked %d; added %d; removed %d; unchanged %d; failed %d.',
            $result['checked'],
            $result['added'],
            $result['removed'],
            $result['unchanged'],
            $result['failed']
        ));

        foreach ($result['errors'] as $error) {
            $this->error($error['email'].': '.$error['message']);
        }

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
