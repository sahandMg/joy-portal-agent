<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class JoyCredentialClient
{
    public function snapshot(): array
    {
        $url = (string) config('xray.joy_credentials_url');
        if ($url === '') {
            throw new RuntimeException('Joy credential synchronization is not configured.');
        }
        $data = $this->signedPost($url, ['node_id' => (string) config('xray.node_id')]);
        if (!is_array($data) || ($data['complete_snapshot'] ?? false) !== true ||
            !is_array($data['inbounds'] ?? null) || !is_array($data['credentials'] ?? null)) {
            throw new RuntimeException('Joy credential API returned an incomplete snapshot.');
        }
        return $data;
    }

    public function reportRuntimeStatus(array $users): array
    {
        $url = (string) config('xray.joy_runtime_status_url');
        if ($url === '') throw new RuntimeException('Joy runtime status URL is not configured.');
        return $this->signedPost($url, [
            'node_id' => (string) config('xray.node_id'),
            'users' => $users,
        ]);
    }

    private function signedPost(string $url, array $payload): array
    {
        $agentId = (string) config('xray.joy_agent_id');
        $secret = (string) config('xray.joy_agent_secret');
        if ($agentId === '' || $secret === '') {
            throw new RuntimeException('Joy agent authentication is not configured.');
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) throw new RuntimeException('Could not encode Joy API request.');
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $agentId."\n".$timestamp."\n".$body, $secret);
        $response = Http::timeout((int) config('xray.joy_timeout', 10))->withHeaders([
            'X-Joy-Agent' => $agentId, 'X-Joy-Timestamp' => $timestamp,
            'X-Joy-Signature' => $signature, 'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->send('POST', $url, ['body' => $body]);
        if (!$response->successful()) {
            throw new RuntimeException('Joy API returned HTTP '.$response->status().': '.
                mb_substr($response->body(), 0, 500));
        }
        return (array) $response->json('data');
    }
}
