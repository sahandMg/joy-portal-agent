<?php

namespace App\Services;

use App\Models\XrayRuntimeInbound;
use Throwable;

final class XrayRuntimeInboundReconciler
{
    public function __construct(private XrayInboundManager $manager) {}

    public function reconcile(bool $removeInactive = true): array
    {
        $known = $this->manager->profiles();
        $result = ['checked' => 0, 'added' => 0, 'removed' => 0,
            'unchanged' => 0, 'failed' => 0, 'errors' => []];

        XrayRuntimeInbound::query()->orderBy('id')->chunkById(100,
            function ($inbounds) use (&$known, &$result, $removeInactive): void {
                foreach ($inbounds as $inbound) {
                    $result['checked']++;
                    $exists = isset($known[$inbound->tag]);
                    try {
                        if ($exists && $inbound->is_active &&
                            !$this->matches($known[$inbound->tag], $inbound)) {
                            throw new \RuntimeException(
                                'Existing Xray inbound does not match desired protocol/transport/port/path.'
                            );
                        }
                        if ($inbound->is_active && !$exists) {
                            $this->manager->add($inbound);
                            $known[$inbound->tag] = [
                                'protocol' => $inbound->protocol, 'transport' => $inbound->transport,
                                'port' => $inbound->port, 'ws_path' => $inbound->ws_path,
                            ];
                            $result['added']++;
                        } elseif (!$inbound->is_active && $exists && $removeInactive) {
                            $this->manager->remove($inbound->tag);
                            unset($known[$inbound->tag]);
                            $result['removed']++;
                        } else {
                            $result['unchanged']++;
                        }
                        $inbound->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();
                    } catch (Throwable $exception) {
                        $message = mb_substr($exception->getMessage(), 0, 2000);
                        $inbound->forceFill(['last_error' => $message])->save();
                        $result['failed']++;
                        $result['errors'][] = ['tag' => $inbound->tag, 'message' => $message];
                    }
                }
            });
        return $result;
    }

    private function matches(array $actual, XrayRuntimeInbound $desired): bool
    {
        return ($actual['protocol'] ?? null) === $desired->protocol &&
            ($actual['transport'] ?? null) === $desired->transport &&
            (int) ($actual['port'] ?? 0) === $desired->port &&
            ($desired->transport !== 'ws' ||
                ($actual['ws_path'] ?? '/') === ($desired->ws_path ?: '/'));
    }
}
