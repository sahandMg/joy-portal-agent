<?php

namespace App\Console\Commands;

use App\Models\XrayUsageSnapshot;
use App\Services\XrayUserManager;
use Illuminate\Console\Command;
use Throwable;

class AddXrayUser extends Command
{
    protected $signature = 'xray:users:add
        {tag : Existing inbound tag}
        {protocol : vless or vmess}
        {uuid : Client UUID}
        {email : Unique Xray statistics email}
        {--port= : Inbound listen port; inferred from tags like inbound-20180}
        {--level=0}
        {--alter-id=0 : VMess alterId}
        {--force : Skip interactive confirmation}';
    protected $description = 'Add one runtime user to an existing Xray inbound';

    public function handle(XrayUserManager $manager): int
    {
        $tag = (string) $this->argument('tag');
        $protocol = strtolower((string) $this->argument('protocol'));
        $uuid = strtolower((string) $this->argument('uuid'));
        $email = (string) $this->argument('email');
        $port = $this->option('port');

        if (($port === null || $port === '') && preg_match('/(?:^|-)inbound-(\d+)$/', $tag, $matches)) {
            $port = $matches[1];
        }

        $port = (int) $port;

        if (!in_array($protocol, ['vless', 'vmess'], true)) {
            $this->error('Protocol must be vless or vmess.');
            return self::INVALID;
        }
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            $this->error('UUID is invalid.');
            return self::INVALID;
        }
        if (!preg_match('/^[A-Za-z0-9._:@-]{3,128}$/', $email)) {
            $this->error('Email/stat key contains unsupported characters.');
            return self::INVALID;
        }
        if ($port < 1 || $port > 65535) {
            $this->error('A valid inbound port is required. Pass --port=20180.');
            return self::INVALID;
        }

        $this->warn('This modifies running Xray and is not persisted after restart.');
        $this->line("Inbound: {$tag}; port: {$port}; protocol: {$protocol}; email: {$email}");
        if (!$this->option('force') && !$this->confirm('Add this test user?', false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $payload = $manager->add(
                $tag,
                $protocol,
                $uuid,
                $email,
                $port,
                max(0, (int) $this->option('level')),
                max(0, (int) $this->option('alter-id'))
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Xray added the runtime user and the inbound user count increased.');
        XrayUsageSnapshot::query()->firstOrCreate(
            ['email' => $email],
            [
                'uplink_total_bytes' => 0,
                'downlink_total_bytes' => 0,
                'observed_at' => now(),
            ]
        );
        $this->warn('An empty object in readback can be a CLI/Core serialization limitation.');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
