<?php

namespace Tests\Unit;

use App\Services\XrayStatsReader;
use PHPUnit\Framework\TestCase;

class XrayStatsReaderTest extends TestCase
{
    public function test_it_groups_user_counters_by_email(): void
    {
        $json = json_encode([
            'stat' => [
                ['name' => 'user>>>ios:1001>>>traffic>>>uplink', 'value' => '100'],
                ['name' => 'user>>>ios:1001>>>traffic>>>downlink', 'value' => '900'],
                ['name' => 'user>>>ios:1002>>>traffic>>>downlink', 'value' => '250'],
                ['name' => 'inbound>>>external>>>traffic>>>downlink', 'value' => '5000'],
            ],
        ]);

        $this->assertSame([
            'ios:1001' => ['uplink' => 100, 'downlink' => 900],
            'ios:1002' => ['uplink' => 0, 'downlink' => 250],
        ], (new XrayStatsReader())->parse($json));
    }
}
