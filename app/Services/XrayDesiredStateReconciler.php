<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

final class XrayDesiredStateReconciler
{
    public function __construct(
        private XrayRuntimeInboundReconciler $inbounds,
        private XrayRuntimeUserReconciler $users,
    ) {}

    public function reconcile(): array
    {
        return Cache::lock('xray-desired-state-reconcile', 30)->block(10, function (): array {
            $inboundEnsure = $this->inbounds->reconcile(false);
            $users = $this->users->reconcile();
            $inboundCleanup = $this->inbounds->reconcile(true);
            return compact('inboundEnsure', 'users', 'inboundCleanup');
        });
    }
}
