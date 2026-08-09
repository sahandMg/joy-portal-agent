<?php

namespace App\Services;

use App\Models\PortalUsageSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class PortalUsageSessionTracker
{
    public function __construct(private JoyUsageClient $client) {}

    public function process(array $collection): array
    {
        $changed = 0;
        foreach ($collection['users'] ?? [] as $row) {
            $delta = max(0, (int) ($row['total_delta_bytes'] ?? 0));
            if ($delta === 0) continue;

            DB::transaction(function () use ($row, $delta, &$changed) {
                $email = (string) $row['email'];
                $session = PortalUsageSession::query()->where('email', $email)
                    ->whereNull('closed_at')->lockForUpdate()->first();

                if ($session && !empty($row['counter_reset_detected'])) {
                    $session->update(['closed_at' => now()]);
                    $session = null;
                }
                if (!$session) {
                    $session = PortalUsageSession::query()->create([
                        'email' => $email,
                        'session_id' => (string) Str::uuid(),
                        'last_activity_at' => now(),
                    ]);
                }

                $session->update([
                    'sequence' => (int) $session->sequence + 1,
                    'total_bytes' => (int) $session->total_bytes + $delta,
                    'last_activity_at' => now(),
                ]);
                $changed++;
            }, 3);
        }

        $closed = PortalUsageSession::query()->whereNull('closed_at')
            ->where('last_activity_at', '<=', now()->subSeconds((int) config('xray.session_idle_timeout', 180)))
            ->update(['closed_at' => now(), 'updated_at' => now()]);

        return ['changed' => $changed, 'closed' => $closed] + $this->flushPending();
    }

    public function flushPending(): array
    {
        $pending = PortalUsageSession::query()
            ->whereColumn('last_reported_total_bytes', '<', 'total_bytes')
            ->orderBy('id')->limit((int) config('xray.joy_batch_size', 200))->get();
        if ($pending->isEmpty()) return ['reported' => 0, 'rejected' => 0];

        try {
            $results = collect($this->client->report($pending))->keyBy(
                fn (array $result) => $result['session_id'] ?? ''
            );
        } catch (Throwable $exception) {
            PortalUsageSession::query()->whereIn('id', $pending->pluck('id'))
                ->update(['last_error' => mb_substr($exception->getMessage(), 0, 2000), 'updated_at' => now()]);
            report($exception);
            return ['reported' => 0, 'rejected' => $pending->count()];
        }

        $reported = 0;
        $rejected = 0;
        foreach ($pending as $session) {
            $result = $results->get($session->session_id);
            if (is_array($result) && ($result['accepted'] ?? false)) {
                $session->update([
                    'last_reported_total_bytes' => $session->total_bytes,
                    'last_reported_at' => now(), 'last_error' => null,
                ]);
                $reported++;
            } else {
                $session->update(['last_error' => (string) ($result['reason'] ?? 'missing_result')]);
                $rejected++;
            }
        }
        return ['reported' => $reported, 'rejected' => $rejected];
    }
}
