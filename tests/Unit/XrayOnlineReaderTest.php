<?php

namespace Tests\Unit;

use App\Services\XrayOnlineReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class XrayOnlineReaderTest extends TestCase
{
    public function test_it_parses_an_online_user(): void
    {
        $json = json_encode(['stat' => [
            'name' => 'user>>>joy:test-user-a>>>online',
            'value' => 1,
        ]]);

        $this->assertTrue((new XrayOnlineReader())->parse($json, 'joy:test-user-a'));
    }

    public function test_zero_online_sessions_is_offline(): void
    {
        $json = json_encode(['stat' => [
            'name' => 'user>>>joy:test-user-a>>>online',
            'value' => 0,
        ]]);

        $this->assertFalse((new XrayOnlineReader())->parse($json, 'joy:test-user-a'));
    }

    public function test_it_rejects_a_response_for_another_user(): void
    {
        $this->expectException(RuntimeException::class);
        (new XrayOnlineReader())->parse(
            '{"stat":{"name":"user>>>joy:other>>>online","value":1}}',
            'joy:test-user-a'
        );
    }
}
