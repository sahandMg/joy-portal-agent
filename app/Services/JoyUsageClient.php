<?php

namespace App\Services;

use App\Models\PortalUsageSession;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class JoyUsageClient
{
    /** @param iterable<PortalUsageSession> $sessions */
    public function report(iterable $sessions): array
    {
        $agentId = (string) config('xray.joy_agent_id');
        $secret = (string) config('xray.joy_agent_secret');
        $url = (string) config('xray.joy_usage_url');
        if ($agentId === '' || $secret === '' || $url === '') {
            throw new RuntimeException('Joy usage synchronization is not configured.');
        }

        $reports = collect($sessions)->map(fn (PortalUsageSession $session) => [
            'email' => $session->email,
            'session_id' => $session->session_id,
            'sequence' => max(1, (int) $session->sequence),
            'total_bytes' => (int) $session->total_bytes,
            'online' => $session->closed_at === null,
        ])->values()->all();
        if ($reports === []) return [];

        $body = json_encode([
            'node_id' => (string) config('xray.node_id'),
            'reports' => $reports,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) throw new RuntimeException('Could not encode usage batch.');

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $agentId."\n".$timestamp."\n".$body, $secret);
        $response = Http::timeout((int) config('xray.joy_timeout', 10))
            ->withHeaders([
                'X-Joy-Agent' => $agentId,
                'X-Joy-Timestamp' => $timestamp,
                'X-Joy-Signature' => $signature,
                'Accept' => 'application/json',
            ])->withBody($body, 'application/json')->post($url);

        if (!$response->successful()) {
            throw new RuntimeException('Joy usage API returned HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500));
        }

        $results = $response->json('data.results');
        if (!is_array($results)) throw new RuntimeException('Joy usage API returned an invalid response.');
        return $results;
    }
}
