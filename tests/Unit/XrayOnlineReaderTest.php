<?php

namespace Tests\Unit;

use App\Services\XrayOnlineReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class XrayOnlineReaderTest extends TestCase
{
    public function test_it_parses_an_online_user_ip_list(): void
    {
        $json = json_encode([
            'name' => 'user>>>joy:test-user-a>>>online',
            'ips' => [['ip' => '203.0.113.10', 'lastSeen' => 123456]],
        ]);

        $this->assertTrue((new XrayOnlineReader())->parseIpList($json, 'joy:test-user-a'));
    }

    public function test_an_empty_ip_list_is_offline_even_if_scalar_counter_was_stale(): void
    {
        $json = json_encode([
            'name' => 'user>>>joy:test-user-a>>>online',
        ]);

        $this->assertFalse((new XrayOnlineReader())->parseIpList($json, 'joy:test-user-a'));
    }

    public function test_it_accepts_an_ip_with_a_port(): void
    {
        $json = json_encode([
            'name' => 'user>>>joy:test-user-a>>>online',
            'ips' => ['203.0.113.10:443'],
        ]);

        $this->assertTrue((new XrayOnlineReader())->parseIpList($json, 'joy:test-user-a'));
    }

    public function test_it_rejects_a_response_for_another_user(): void
    {
        $this->expectException(RuntimeException::class);
        (new XrayOnlineReader())->parseIpList(
            '{"name":"user>>>joy:other>>>online","ips":["203.0.113.10"]}',
            'joy:test-user-a'
        );
    }
}
