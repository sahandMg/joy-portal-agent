<?php

namespace Tests\Unit;

use App\Models\XrayRuntimeInbound;
use App\Services\XrayInboundManager;
use App\Services\XrayRuntimeInboundReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class XrayRuntimeInboundReconcilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_missing_active_inbound(): void
    {
        $inbound = XrayRuntimeInbound::query()->create([
            'tag' => 'inbound-vless-ws-20181', 'protocol' => 'vless',
            'transport' => 'ws', 'port' => 20181, 'ws_path' => '/ios', 'is_active' => true,
        ]);
        $manager = Mockery::mock(XrayInboundManager::class);
        $manager->shouldReceive('profiles')->once()->andReturn([]);
        $manager->shouldReceive('add')->once()->with(
            Mockery::on(fn ($value) => $value->is($inbound))
        );

        $result = (new XrayRuntimeInboundReconciler($manager))->reconcile(false);
        $this->assertSame(1, $result['added']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotNull($inbound->fresh()->last_synced_at);
    }

    public function test_it_removes_an_inactive_managed_inbound(): void
    {
        XrayRuntimeInbound::query()->create([
            'tag' => 'inbound-vmess-tcp-20182', 'protocol' => 'vmess',
            'transport' => 'tcp', 'port' => 20182, 'is_active' => false,
        ]);
        $manager = Mockery::mock(XrayInboundManager::class);
        $manager->shouldReceive('profiles')->once()->andReturn([
            'inbound-vmess-tcp-20182' => [
                'protocol' => 'vmess', 'transport' => 'tcp', 'port' => 20182, 'ws_path' => null,
            ],
        ]);
        $manager->shouldReceive('remove')->once()->with('inbound-vmess-tcp-20182');

        $result = (new XrayRuntimeInboundReconciler($manager))->reconcile(true);
        $this->assertSame(1, $result['removed']);
        $this->assertSame(0, $result['failed']);
    }
}
