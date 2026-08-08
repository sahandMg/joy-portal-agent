<?php

namespace App\Services;

use App\Models\XrayUsageSample;
use App\Models\XrayUsageSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class XrayUsageCollector
{
    public function __construct(private XrayStatsReader $reader)
    {
    }

    /**
     * @return array{collection_id:string, first_observation:bool, users:array<int, array<string, mixed>>}
     */
    public function collect(): array
    {
        $current = $this->reader->read();
        $collectionId = (string) Str::uuid();
        $observedAt = now();
        $rows = [];

        foreach ($current as $email => $counters) {
            $rows[] = DB::transaction(function () use (
                $collectionId,
                $observedAt,
                $email,
                $counters
            ) {
                $snapshot = XrayUsageSnapshot::query()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                $firstObservation = $snapshot === null;
                $previousUp = (int) ($snapshot?->uplink_total_bytes ?? 0);
                $previousDown = (int) ($snapshot?->downlink_total_bytes ?? 0);
                $resetDetected = !$firstObservation && (
                    $counters['uplink'] < $previousUp ||
                    $counters['downlink'] < $previousDown
                );

                // The first observation establishes a baseline and is never billable.
                // After a reset, current counters are the bytes observed since Xray restarted.
                $upDelta = $firstObservation
                    ? 0
                    : ($counters['uplink'] >= $previousUp
                        ? $counters['uplink'] - $previousUp
                        : $counters['uplink']);
                $downDelta = $firstObservation
                    ? 0
                    : ($counters['downlink'] >= $previousDown
                        ? $counters['downlink'] - $previousDown
                        : $counters['downlink']);

                XrayUsageSnapshot::query()->updateOrCreate(
                    ['email' => $email],
                    [
                        'uplink_total_bytes' => $counters['uplink'],
                        'downlink_total_bytes' => $counters['downlink'],
                        'observed_at' => $observedAt,
                    ]
                );

                XrayUsageSample::query()->create([
                    'collection_id' => $collectionId,
                    'email' => $email,
                    'uplink_total_bytes' => $counters['uplink'],
                    'downlink_total_bytes' => $counters['downlink'],
                    'uplink_delta_bytes' => $upDelta,
                    'downlink_delta_bytes' => $downDelta,
                    'counter_reset_detected' => $resetDetected,
                    'observed_at' => $observedAt,
                ]);

                return [
                    'email' => $email,
                    'uplink_total_bytes' => $counters['uplink'],
                    'downlink_total_bytes' => $counters['downlink'],
                    'uplink_delta_bytes' => $upDelta,
                    'downlink_delta_bytes' => $downDelta,
                    'total_delta_bytes' => $upDelta + $downDelta,
                    'first_observation' => $firstObservation,
                    'counter_reset_detected' => $resetDetected,
                ];
            }, 3);
        }

        return [
            'collection_id' => $collectionId,
            'first_observation' => collect($rows)->contains('first_observation', true),
            'users' => $rows,
        ];
    }
}
