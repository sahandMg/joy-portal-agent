<?php

namespace Tests\Unit;

use App\Services\JoyCredentialClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class JoyCredentialClientTest extends TestCase
{
    public function test_it_requests_a_complete_signed_node_snapshot(): void
    {
        config()->set('xray.joy_agent_id', 'portal-1');
        config()->set('xray.joy_agent_secret', 'test-secret');
        config()->set('xray.joy_credentials_url', 'https://app2.example/api/snapshot');
        config()->set('xray.node_id', 'portal-br217');

        Http::fake(['https://app2.example/api/snapshot' => Http::response([
            'data' => [
                'node_id' => 'portal-br217',
                'complete_snapshot' => true,
                'inbounds' => [],
                'credentials' => [],
            ],
        ], 200),
        ]);

        $data = (new JoyCredentialClient())->snapshot();
        $this->assertTrue($data['complete_snapshot']);

        Http::assertSent(function (Request $request): bool {
            $timestamp = $request->header('X-Joy-Timestamp')[0] ?? '';
            $body = $request->body();
            $expected = hash_hmac('sha256', "portal-1\n{$timestamp}\n{$body}", 'test-secret');
            return $request->url() === 'https://app2.example/api/snapshot' &&
                $request->method() === 'POST' &&
                ($request->header('X-Joy-Agent')[0] ?? '') === 'portal-1' &&
                ($request->header('X-Joy-Signature')[0] ?? '') === $expected &&
                json_decode($body, true) === ['node_id' => 'portal-br217'];
        });
    }
}
