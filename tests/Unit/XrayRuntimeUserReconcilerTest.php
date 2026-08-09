<?php

namespace Tests\Unit;

use App\Models\XrayRuntimeUser;
use App\Services\XrayRuntimeUserReconciler;
use App\Services\XrayUserManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class XrayRuntimeUserReconcilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_missing_active_users_and_removes_present_disabled_users(): void
    {
        $active = XrayRuntimeUser::query()->create([
            'inbound_tag' => 'inbound-20180',
            'protocol' => 'vmess',
            'uuid' => '6068ed6d-736e-491b-b267-55fd93f7ad15',
            'email' => 'joy:active',
            'port' => 20180,
            'level' => 0,
            'alter_id' => 0,
            'is_active' => true,
        ]);
        $disabled = XrayRuntimeUser::query()->create([
            'inbound_tag' => 'inbound-20180',
            'protocol' => 'vmess',
            'uuid' => '8068ed6d-736e-491b-b267-55fd93f7ad15',
            'email' => 'joy:disabled',
            'port' => 20180,
            'level' => 0,
            'alter_id' => 0,
            'is_active' => false,
        ]);

        $manager = Mockery::mock(XrayUserManager::class);
        $manager->shouldReceive('exists')->once()->with('inbound-20180', 'joy:active')->andReturnFalse();
        $manager->shouldReceive('add')->once()->with(
            'inbound-20180',
            'vmess',
            '6068ed6d-736e-491b-b267-55fd93f7ad15',
            'joy:active',
            20180,
            0,
            0
        )->andReturn([]);
        $manager->shouldReceive('exists')->once()->with('inbound-20180', 'joy:disabled')->andReturnTrue();
        $manager->shouldReceive('remove')->once()->with('inbound-20180', 'joy:disabled');

        $result = (new XrayRuntimeUserReconciler($manager))->reconcile();

        $this->assertSame(2, $result['checked']);
        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['removed']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotNull($active->fresh()->last_synced_at);
        $this->assertNotNull($disabled->fresh()->last_synced_at);
    }

    public function test_it_records_a_failure_and_keeps_the_desired_user_for_retry(): void
    {
        $user = XrayRuntimeUser::query()->create([
            'inbound_tag' => 'inbound-20180',
            'protocol' => 'vmess',
            'uuid' => '6068ed6d-736e-491b-b267-55fd93f7ad15',
            'email' => 'joy:retry',
            'port' => 20180,
            'is_active' => true,
        ]);

        $manager = Mockery::mock(XrayUserManager::class);
        $manager->shouldReceive('exists')->once()->andThrow(new \RuntimeException('Xray unavailable'));

        $result = (new XrayRuntimeUserReconciler($manager))->reconcile();

        $this->assertSame(1, $result['failed']);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertSame('Xray unavailable', $user->fresh()->last_error);
    }
}
